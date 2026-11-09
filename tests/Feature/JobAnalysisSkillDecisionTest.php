<?php

namespace Tests\Feature;

use App\Models\JobAnalysis;
use App\Models\JobAnalysisSkillDecision;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class JobAnalysisSkillDecisionTest extends TestCase
{
    use RefreshDatabase;

    private function createCompletedAnalysis(User $user, array $overrides = []): JobAnalysis
    {
        return JobAnalysis::query()->create(array_merge([
            'user_id' => $user->id,
            'job_title' => 'React Native Developer',
            'company_name' => 'Acme',
            'job_description' => str_repeat('React Native developer role with JavaScript and TypeScript. ', 3),
            'status' => JobAnalysis::STATUS_COMPLETED,
            'provider' => 'grok',
            'model' => 'test-model',
            'analysis_json' => [
                'job_title' => 'React Native Developer',
                'job_category' => 'Mobile',
                'required_skills' => ['React Native', 'JavaScript', 'REST API'],
                'preferred_skills' => ['TypeScript'],
                'frameworks_libraries' => [],
                'databases' => [],
                'tools_platforms' => [],
                'experience' => ['minimum_years' => 2, 'maximum_years' => 3, 'summary' => null],
                'education_requirements' => [],
                'responsibilities' => [],
                'technical_keywords' => [],
                'work_arrangement' => null,
                'location' => null,
                'notes' => null,
            ],
        ], $overrides));
    }

    public function test_add_to_master_profile_requires_confirmation(): void
    {
        $user = User::factory()->create();
        $analysis = $this->createCompletedAnalysis($user);

        $this->actingAs($user)
            ->from(route('job-analyzer.show', $analysis))
            ->post(route('job-analyzer.skill-decisions.store', $analysis), [
                'skill_name' => 'JavaScript',
                'requirement_type' => 'required',
                'decision' => 'add_to_profile',
                'confirmed' => false,
            ])
            ->assertSessionHasErrors('confirmed');

        $this->assertDatabaseCount('profile_skills', 0);
        $this->assertDatabaseCount('job_analysis_skill_decisions', 0);
    }

    public function test_add_to_master_profile_attaches_skill_and_recalculates(): void
    {
        Http::fake(); // ensure no Grok calls
        $user = User::factory()->create();
        $analysis = $this->createCompletedAnalysis($user);

        $this->actingAs($user)
            ->post(route('job-analyzer.skill-decisions.store', $analysis), [
                'skill_name' => 'JavaScript',
                'requirement_type' => 'required',
                'decision' => 'add_to_profile',
                'confirmed' => true,
            ])
            ->assertRedirect(route('job-analyzer.show', $analysis));

        $this->assertDatabaseHas('skills', ['slug' => 'javascript']);
        $this->assertDatabaseCount('profile_skills', 1);
        $this->assertDatabaseHas('job_analysis_skill_decisions', [
            'job_analysis_id' => $analysis->id,
            'normalized_skill_key' => 'javascript',
            'decision' => JobAnalysisSkillDecision::DECISION_ADD_TO_PROFILE,
            'confirmation_status' => JobAnalysisSkillDecision::CONFIRMATION_USER_CONFIRMED,
        ]);

        $this->actingAs($user)
            ->get(route('job-analyzer.show', $analysis))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('JobAnalyzer/Show')
                ->where('analysis.comparison.matched_required', fn ($skills) => collect($skills)->contains('JavaScript'))
                ->where('analysis.skill_review.missing_required', fn ($rows) => collect($rows)->every(
                    fn ($row) => $row['name'] !== 'JavaScript'
                ))
            );

        Http::assertNothingSent();
    }

    public function test_resume_only_does_not_modify_master_profile(): void
    {
        $user = User::factory()->create();
        $analysis = $this->createCompletedAnalysis($user);

        $this->actingAs($user)
            ->post(route('job-analyzer.skill-decisions.store', $analysis), [
                'skill_name' => 'REST API',
                'requirement_type' => 'required',
                'decision' => 'resume_only',
                'confirmed' => true,
            ])
            ->assertRedirect(route('job-analyzer.show', $analysis));

        $this->assertDatabaseCount('profile_skills', 0);
        $this->assertDatabaseHas('job_analysis_skill_decisions', [
            'normalized_skill_key' => 'rest api',
            'decision' => JobAnalysisSkillDecision::DECISION_RESUME_ONLY,
        ]);

        $this->actingAs($user)
            ->get(route('job-analyzer.show', $analysis))
            ->assertInertia(fn (Assert $page) => $page
                ->where('analysis.resume_skill_context.approved_resume_only_skills', ['REST API'])
                ->where('analysis.comparison.missing_required', fn ($skills) => collect($skills)->contains('REST API'))
            );
    }

    public function test_ignore_and_keep_pending_are_reversible(): void
    {
        $user = User::factory()->create();
        $analysis = $this->createCompletedAnalysis($user);

        $this->actingAs($user)->post(route('job-analyzer.skill-decisions.store', $analysis), [
            'skill_name' => 'TypeScript',
            'requirement_type' => 'preferred',
            'decision' => 'ignore',
        ])->assertRedirect();

        $this->assertDatabaseHas('job_analysis_skill_decisions', [
            'normalized_skill_key' => 'typescript',
            'decision' => JobAnalysisSkillDecision::DECISION_IGNORE,
        ]);

        $this->actingAs($user)->post(route('job-analyzer.skill-decisions.store', $analysis), [
            'skill_name' => 'TypeScript',
            'requirement_type' => 'preferred',
            'decision' => 'pending',
        ])->assertRedirect();

        $this->assertDatabaseHas('job_analysis_skill_decisions', [
            'normalized_skill_key' => 'typescript',
            'decision' => JobAnalysisSkillDecision::DECISION_PENDING,
        ]);

        $this->actingAs($user)
            ->get(route('job-analyzer.show', $analysis))
            ->assertInertia(fn (Assert $page) => $page
                ->where('analysis.resume_skill_context.pending_skills', fn ($skills) => collect($skills)->contains('TypeScript'))
                ->where('analysis.resume_skill_context.ignored_skills', [])
            );
    }

    public function test_changing_resume_only_to_profile_works(): void
    {
        $user = User::factory()->create();
        $analysis = $this->createCompletedAnalysis($user);

        $this->actingAs($user)->post(route('job-analyzer.skill-decisions.store', $analysis), [
            'skill_name' => 'JavaScript',
            'requirement_type' => 'required',
            'decision' => 'resume_only',
            'confirmed' => true,
        ]);

        $this->actingAs($user)->post(route('job-analyzer.skill-decisions.store', $analysis), [
            'skill_name' => 'JavaScript',
            'requirement_type' => 'required',
            'decision' => 'add_to_profile',
            'confirmed' => true,
        ])->assertRedirect();

        $this->assertDatabaseCount('profile_skills', 1);
        $this->assertDatabaseHas('job_analysis_skill_decisions', [
            'normalized_skill_key' => 'javascript',
            'decision' => JobAnalysisSkillDecision::DECISION_ADD_TO_PROFILE,
        ]);
    }

    public function test_resetting_decision_does_not_remove_global_profile_skill(): void
    {
        $user = User::factory()->create();
        $analysis = $this->createCompletedAnalysis($user);

        $this->actingAs($user)->post(route('job-analyzer.skill-decisions.store', $analysis), [
            'skill_name' => 'JavaScript',
            'requirement_type' => 'required',
            'decision' => 'add_to_profile',
            'confirmed' => true,
        ]);

        $this->assertDatabaseCount('profile_skills', 1);

        $this->actingAs($user)->post(route('job-analyzer.skill-decisions.store', $analysis), [
            'skill_name' => 'JavaScript',
            'requirement_type' => 'required',
            'decision' => 'pending',
        ])->assertRedirect();

        $this->assertDatabaseCount('profile_skills', 1);
        $this->assertDatabaseHas('skills', ['slug' => 'javascript']);
        $this->assertDatabaseHas('job_analysis_skill_decisions', [
            'decision' => JobAnalysisSkillDecision::DECISION_PENDING,
        ]);
    }

    public function test_duplicate_profile_add_is_idempotent(): void
    {
        $user = User::factory()->create();
        $analysis = $this->createCompletedAnalysis($user);

        $payload = [
            'skill_name' => 'JavaScript',
            'requirement_type' => 'required',
            'decision' => 'add_to_profile',
            'confirmed' => true,
        ];

        $this->actingAs($user)->post(route('job-analyzer.skill-decisions.store', $analysis), $payload)->assertRedirect();
        $this->actingAs($user)->post(route('job-analyzer.skill-decisions.store', $analysis), $payload)->assertRedirect();

        $this->assertDatabaseCount('skills', 1);
        $this->assertDatabaseCount('profile_skills', 1);
        $this->assertDatabaseCount('job_analysis_skill_decisions', 1);
    }

    public function test_ownership_isolation_and_unauthorized(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $analysis = $this->createCompletedAnalysis($owner);

        $this->post(route('job-analyzer.skill-decisions.store', $analysis), [
            'skill_name' => 'JavaScript',
            'requirement_type' => 'required',
            'decision' => 'ignore',
        ])->assertRedirect(route('login'));

        $this->actingAs($intruder)
            ->post(route('job-analyzer.skill-decisions.store', $analysis), [
                'skill_name' => 'JavaScript',
                'requirement_type' => 'required',
                'decision' => 'ignore',
            ])
            ->assertNotFound();
    }

    public function test_invalid_decision_and_unknown_skill_are_rejected(): void
    {
        $user = User::factory()->create();
        $analysis = $this->createCompletedAnalysis($user);

        $this->actingAs($user)
            ->from(route('job-analyzer.show', $analysis))
            ->post(route('job-analyzer.skill-decisions.store', $analysis), [
                'skill_name' => 'JavaScript',
                'requirement_type' => 'required',
                'decision' => 'invented',
            ])
            ->assertSessionHasErrors('decision');

        $this->actingAs($user)
            ->from(route('job-analyzer.show', $analysis))
            ->post(route('job-analyzer.skill-decisions.store', $analysis), [
                'skill_name' => 'Cobol',
                'requirement_type' => 'required',
                'decision' => 'ignore',
            ])
            ->assertSessionHasErrors('skill_name');
    }

    public function test_skill_aliases_normalize_before_decision(): void
    {
        $user = User::factory()->create();
        $analysis = $this->createCompletedAnalysis($user);

        $this->actingAs($user)->post(route('job-analyzer.skill-decisions.store', $analysis), [
            'skill_name' => 'JS',
            'requirement_type' => 'required',
            'decision' => 'resume_only',
            'confirmed' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('job_analysis_skill_decisions', [
            'canonical_skill_name' => 'JavaScript',
            'normalized_skill_key' => 'javascript',
            'decision' => JobAnalysisSkillDecision::DECISION_RESUME_ONLY,
        ]);
    }

    public function test_original_analysis_json_is_not_mutated(): void
    {
        $user = User::factory()->create();
        $analysis = $this->createCompletedAnalysis($user);
        $original = $analysis->analysis_json;

        $this->actingAs($user)->post(route('job-analyzer.skill-decisions.store', $analysis), [
            'skill_name' => 'JavaScript',
            'requirement_type' => 'required',
            'decision' => 'add_to_profile',
            'confirmed' => true,
        ]);

        $this->assertSame($original, $analysis->fresh()->analysis_json);
    }

    public function test_required_vs_preferred_decisions_are_separate(): void
    {
        $user = User::factory()->create();
        $analysis = $this->createCompletedAnalysis($user, [
            'analysis_json' => [
                'job_title' => 'Dev',
                'job_category' => null,
                'required_skills' => ['TypeScript'],
                'preferred_skills' => ['TypeScript'],
                'frameworks_libraries' => [],
                'databases' => [],
                'tools_platforms' => [],
                'experience' => ['minimum_years' => null, 'maximum_years' => null, 'summary' => null],
                'education_requirements' => [],
                'responsibilities' => [],
                'technical_keywords' => [],
                'work_arrangement' => null,
                'location' => null,
                'notes' => null,
            ],
        ]);

        // Schema normally strips preferred duplicates; still allow distinct rows if present.
        $this->actingAs($user)->post(route('job-analyzer.skill-decisions.store', $analysis), [
            'skill_name' => 'TypeScript',
            'requirement_type' => 'required',
            'decision' => 'ignore',
        ])->assertRedirect();

        $this->actingAs($user)->post(route('job-analyzer.skill-decisions.store', $analysis), [
            'skill_name' => 'TypeScript',
            'requirement_type' => 'preferred',
            'decision' => 'resume_only',
            'confirmed' => true,
        ])->assertRedirect();

        $this->assertDatabaseCount('job_analysis_skill_decisions', 2);
    }

    public function test_existing_profile_skill_shows_as_matched(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('skills.store'), [
            'name' => 'React Native',
            'category' => 'Mobile Development',
        ]);

        $analysis = $this->createCompletedAnalysis($user);

        $this->actingAs($user)
            ->get(route('job-analyzer.show', $analysis))
            ->assertInertia(fn (Assert $page) => $page
                ->where('analysis.comparison.matched_required', ['React Native'])
                ->where('analysis.skill_review.matched_required.0.name', 'React Native')
                ->where('analysis.skill_review.matched_required.0.in_master_profile', true)
            );

        $this->assertTrue(Skill::query()->where('slug', 'react-native')->exists());
    }
}
