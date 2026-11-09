<?php

namespace Tests\Feature;

use App\Models\JobAnalysis;
use App\Models\JobAnalysisProjectSelection;
use App\Models\Project;
use App\Models\ProjectTechnology;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class JobAnalysisProjectSelectionTest extends TestCase
{
    use RefreshDatabase;

    private function createProject(User $user, string $name, array $techs, array $flags = []): Project
    {
        $project = Project::query()->create(array_merge([
            'user_id' => $user->id,
            'name' => $name,
            'short_description' => $name.' short',
            'detailed_description' => $name.' detailed',
            'project_type' => 'Web Application',
            'is_active' => true,
            'include_in_resume' => true,
        ], $flags));

        foreach ($techs as $i => $tech) {
            ProjectTechnology::query()->create([
                'project_id' => $project->id,
                'name' => $tech,
                'sort_order' => $i,
            ]);
        }

        return $project;
    }

    private function createAnalysis(User $user, array $required, array $preferred = []): JobAnalysis
    {
        return JobAnalysis::query()->create([
            'user_id' => $user->id,
            'job_title' => 'Target Role',
            'job_description' => str_repeat('Job description content for analysis testing. ', 4),
            'status' => JobAnalysis::STATUS_COMPLETED,
            'provider' => 'grok',
            'model' => 'test-model',
            'analysis_json' => [
                'job_title' => 'Target Role',
                'job_category' => 'Web',
                'required_skills' => $required,
                'preferred_skills' => $preferred,
                'frameworks_libraries' => $required,
                'databases' => [],
                'tools_platforms' => [],
                'experience' => ['minimum_years' => null, 'maximum_years' => null, 'summary' => null],
                'education_requirements' => [],
                'responsibilities' => ['Build APIs'],
                'technical_keywords' => $required,
                'work_arrangement' => null,
                'location' => null,
                'notes' => null,
            ],
        ]);
    }

    public function test_inactive_and_ineligible_projects_are_excluded(): void
    {
        Http::fake();
        $user = User::factory()->create();
        $active = $this->createProject($user, 'Active Laravel', ['Laravel', 'PHP']);
        $this->createProject($user, 'Inactive', ['Laravel'], ['is_active' => false]);
        $this->createProject($user, 'Not Resume', ['Laravel'], ['include_in_resume' => false]);
        $analysis = $this->createAnalysis($user, ['Laravel', 'PHP']);

        $this->actingAs($user)
            ->get(route('job-analyzer.show', $analysis))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('analysis.project_selection.ranked', fn ($rows) => collect($rows)->pluck('id')->all() === [$active->id])
            );

        Http::assertNothingSent();
    }

    public function test_ranking_orders_by_relevance_with_stable_ties(): void
    {
        $user = User::factory()->create();
        $beta = $this->createProject($user, 'Beta App', ['Laravel']);
        $alpha = $this->createProject($user, 'Alpha App', ['Laravel']);
        $weak = $this->createProject($user, 'Mobile Kit', ['React Native']);
        $analysis = $this->createAnalysis($user, ['Laravel', 'PHP']);

        $this->actingAs($user)
            ->get(route('job-analyzer.show', $analysis))
            ->assertInertia(fn (Assert $page) => $page
                ->where('analysis.project_selection.ranked.0.id', $alpha->id)
                ->where('analysis.project_selection.ranked.1.id', $beta->id)
                ->where('analysis.project_selection.ranked', fn ($rows) => collect($rows)->last()['id'] === $weak->id)
            );
    }

    public function test_manual_selection_save_reorder_and_reset(): void
    {
        $user = User::factory()->create();
        $a = $this->createProject($user, 'API Service', ['Laravel', 'PHP']);
        $b = $this->createProject($user, 'Admin Panel', ['Laravel', 'React.js']);
        $c = $this->createProject($user, 'Mobile Companion', ['React Native']);
        $analysis = $this->createAnalysis($user, ['Laravel', 'PHP'], ['React.js']);

        $this->actingAs($user)
            ->put(route('job-analyzer.project-selection.update', $analysis), [
                'project_ids' => [$c->id, $a->id],
            ])
            ->assertRedirect(route('job-analyzer.show', $analysis));

        $this->assertDatabaseHas('job_analysis_project_selections', [
            'job_analysis_id' => $analysis->id,
            'project_id' => $c->id,
            'display_order' => 0,
            'selection_source' => JobAnalysisProjectSelection::SOURCE_MANUAL,
        ]);
        $this->assertDatabaseHas('job_analysis_project_selections', [
            'project_id' => $a->id,
            'display_order' => 1,
        ]);
        $this->assertDatabaseMissing('job_analysis_project_selections', [
            'project_id' => $b->id,
            'job_analysis_id' => $analysis->id,
        ]);

        $this->actingAs($user)
            ->get(route('job-analyzer.show', $analysis))
            ->assertInertia(fn (Assert $page) => $page
                ->where('analysis.project_selection.has_saved_selection', true)
                ->where('analysis.project_selection.selected.0.id', $c->id)
                ->where('analysis.project_selection.selected.1.id', $a->id)
                ->where('analysis.resume_project_context.projects.0.id', $c->id)
            );

        $this->actingAs($user)
            ->post(route('job-analyzer.project-selection.reset', $analysis))
            ->assertRedirect(route('job-analyzer.show', $analysis));

        $this->assertDatabaseHas('job_analysis_project_selections', [
            'job_analysis_id' => $analysis->id,
            'selection_source' => JobAnalysisProjectSelection::SOURCE_RECOMMENDATION,
        ]);
    }

    public function test_saved_manual_selection_not_silently_overwritten_by_rerank(): void
    {
        $user = User::factory()->create();
        $strong = $this->createProject($user, 'Strong Match', ['Laravel', 'PHP']);
        $weak = $this->createProject($user, 'Weak Match', ['Excel']);
        $analysis = $this->createAnalysis($user, ['Laravel', 'PHP']);

        $this->actingAs($user)->put(route('job-analyzer.project-selection.update', $analysis), [
            'project_ids' => [$weak->id],
        ]);

        $this->actingAs($user)
            ->get(route('job-analyzer.show', $analysis))
            ->assertInertia(fn (Assert $page) => $page
                ->where('analysis.project_selection.selected.0.id', $weak->id)
                ->where('analysis.project_selection.recommended.0.id', $strong->id)
            );
    }

    public function test_cross_user_isolation_and_invalid_ids(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $project = $this->createProject($owner, 'Owned', ['Laravel']);
        $analysis = $this->createAnalysis($owner, ['Laravel']);

        $this->actingAs($intruder)
            ->put(route('job-analyzer.project-selection.update', $analysis), [
                'project_ids' => [$project->id],
            ])
            ->assertNotFound();

        $foreign = $this->createProject($intruder, 'Foreign', ['Laravel']);

        $this->actingAs($owner)
            ->from(route('job-analyzer.show', $analysis))
            ->put(route('job-analyzer.project-selection.update', $analysis), [
                'project_ids' => [$foreign->id],
            ])
            ->assertSessionHasErrors('project_ids');

        $this->actingAs($owner)
            ->from(route('job-analyzer.show', $analysis))
            ->put(route('job-analyzer.project-selection.update', $analysis), [
                'project_ids' => [$project->id, $project->id],
            ])
            ->assertSessionHasErrors('project_ids.0');
    }

    public function test_empty_projects_shows_empty_state_payload(): void
    {
        $user = User::factory()->create();
        $analysis = $this->createAnalysis($user, ['Laravel']);

        $this->actingAs($user)
            ->get(route('job-analyzer.show', $analysis))
            ->assertInertia(fn (Assert $page) => $page
                ->where('analysis.project_selection.ranked', [])
                ->where('analysis.project_selection.empty_reason', fn ($reason) => is_string($reason) && $reason !== '')
            );
    }

    public function test_duplicate_prevention_on_unique_constraint_path(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user, 'Unique Path', ['Laravel', 'PHP']);
        $analysis = $this->createAnalysis($user, ['Laravel', 'PHP']);

        $this->actingAs($user)->put(route('job-analyzer.project-selection.update', $analysis), [
            'project_ids' => [$project->id],
        ])->assertRedirect();

        $this->actingAs($user)->put(route('job-analyzer.project-selection.update', $analysis), [
            'project_ids' => [$project->id],
        ])->assertRedirect();

        $this->assertDatabaseCount('job_analysis_project_selections', 1);
    }

    public function test_score_bounds_present_in_payload(): void
    {
        $user = User::factory()->create();
        $this->createProject($user, 'Bounds', ['Laravel']);
        $analysis = $this->createAnalysis($user, ['Laravel']);

        $this->actingAs($user)
            ->get(route('job-analyzer.show', $analysis))
            ->assertInertia(fn (Assert $page) => $page
                ->where('analysis.project_selection.ranked.0.relevance_score', fn ($score) => $score >= 0 && $score <= 100)
            );
    }
}
