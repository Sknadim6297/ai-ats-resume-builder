<?php

namespace Tests\Feature;

use App\Models\Education;
use App\Models\JobAnalysis;
use App\Models\JobAnalysisProjectSelection;
use App\Models\JobAnalysisSkillDecision;
use App\Models\Profile;
use App\Models\Project;
use App\Models\ProjectTechnology;
use App\Models\ResumeDraft;
use App\Models\User;
use App\Models\WorkExperience;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ResumeDraftGenerationTest extends TestCase
{
    use RefreshDatabase;

    private function configureGrok(): void
    {
        config([
            'ai.default' => 'grok',
            'ai.providers.grok.api_key' => 'test-key',
            'ai.providers.grok.base_url' => 'https://api.x.ai/v1',
            'ai.providers.grok.model' => 'test-model',
            'ai.providers.grok.timeout' => 5,
            'ai.providers.grok.max_retries' => 0,
        ]);
    }

    /**
     * @param  list<string>  $required
     * @param  list<string>  $projectTechs
     */
    private function seedUserWithAnalysis(array $required, array $projectTechs, string $projectName = 'Core App'): array
    {
        $user = User::factory()->create();
        $user->profiles()->where('is_default', true)->firstOrFail()->update([
            'name' => 'Default Profile',
            'full_name' => 'Alex Developer',
            'headline' => 'Software Engineer',
            'email' => 'alex@example.com',
            'phone' => '555-0100',
            'location' => 'Remote',
            'professional_summary' => 'Experienced engineer building reliable software.',
            'years_of_experience' => 4,
        ]);

        foreach ($required as $skillName) {
            $this->actingAs($user)->post(route('skills.store'), [
                'name' => $skillName,
                'category' => 'Programming Languages',
            ]);
        }

        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => $projectName,
            'short_description' => 'Relevant project',
            'detailed_description' => 'Built with approved stack.',
            'project_type' => 'Web Application',
            'is_active' => true,
            'include_in_resume' => true,
        ]);
        foreach ($projectTechs as $i => $tech) {
            ProjectTechnology::query()->create([
                'project_id' => $project->id,
                'name' => $tech,
                'sort_order' => $i,
            ]);
        }

        $experience = WorkExperience::query()->create([
            'user_id' => $user->id,
            'company_name' => 'Acme Corp',
            'job_title' => 'Software Engineer',
            'employment_type' => 'Full-time',
            'location' => 'Remote',
            'start_date' => '2022-01-01',
            'end_date' => null,
            'is_current' => true,
            'responsibilities' => ['Built APIs with the approved stack'],
            'achievements' => ['Improved delivery cadence'],
        ]);

        $education = Education::query()->create([
            'user_id' => $user->id,
            'institution_name' => 'State University',
            'degree' => 'B.S.',
            'field_of_study' => 'Computer Science',
            'start_year' => 2016,
            'end_year' => 2020,
            'grade' => null,
        ]);

        $analysis = JobAnalysis::query()->create([
            'user_id' => $user->id,
            'job_title' => 'Target Developer',
            'company_name' => 'Hiring Co',
            'job_description' => str_repeat('Need an engineer experienced with the listed stack. ', 4),
            'status' => JobAnalysis::STATUS_COMPLETED,
            'provider' => 'grok',
            'model' => 'test-model',
            'analysis_json' => [
                'job_title' => 'Target Developer',
                'job_category' => 'Engineering',
                'required_skills' => $required,
                'preferred_skills' => [],
                'frameworks_libraries' => $required,
                'databases' => [],
                'tools_platforms' => [],
                'experience' => ['minimum_years' => 3, 'maximum_years' => null, 'summary' => null],
                'education_requirements' => [],
                'responsibilities' => ['Build applications'],
                'technical_keywords' => $required,
                'work_arrangement' => null,
                'location' => null,
                'notes' => null,
            ],
        ]);

        JobAnalysisProjectSelection::query()->create([
            'user_id' => $user->id,
            'job_analysis_id' => $analysis->id,
            'project_id' => $project->id,
            'selected' => true,
            'display_order' => 0,
            'selection_source' => JobAnalysisProjectSelection::SOURCE_MANUAL,
        ]);

        return compact('user', 'analysis', 'project', 'experience', 'education');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function fakeAiResume(array $seed, array $overrides = []): void
    {
        $payload = array_replace_recursive([
            'contact' => [
                'full_name' => 'Alex Developer',
                'headline' => 'Software Engineer',
                'email' => 'alex@example.com',
                'phone' => '555-0100',
                'location' => 'Remote',
                'linkedin_url' => null,
                'github_url' => null,
                'portfolio_url' => null,
            ],
            'professional_summary' => 'Software engineer focused on reliable delivery for the target role.',
            'technical_skills' => $seed['analysis']->analysis_json['required_skills'],
            'experience' => [[
                'source_id' => $seed['experience']->id,
                'company_name' => 'Acme Corp',
                'job_title' => 'Software Engineer',
                'employment_type' => 'Full-time',
                'location' => 'Remote',
                'start_date' => '2022-01-01',
                'end_date' => null,
                'is_current' => true,
                'bullets' => ['Built APIs with the approved stack'],
            ]],
            'projects' => [[
                'source_id' => $seed['project']->id,
                'name' => $seed['project']->name,
                'project_type' => 'Web Application',
                'technologies' => $seed['analysis']->analysis_json['required_skills'],
                'bullets' => ['Delivered relevant project features'],
            ]],
            'education' => [[
                'source_id' => $seed['education']->id,
                'institution_name' => 'State University',
                'degree' => 'B.S.',
                'field_of_study' => 'Computer Science',
                'start_year' => 2016,
                'end_year' => 2020,
                'grade' => null,
            ]],
        ], $overrides);

        Http::fake([
            'api.x.ai/*' => Http::response([
                'id' => 'chatcmpl-resume',
                'choices' => [[
                    'message' => ['content' => json_encode($payload)],
                ]],
                'usage' => ['total_tokens' => 20],
            ], 200),
        ]);
    }

    public function test_laravel_resume_generation_is_grounded(): void
    {
        $this->configureGrok();
        $seed = $this->seedUserWithAnalysis(['Laravel', 'PHP', 'MySQL'], ['Laravel', 'PHP', 'MySQL'], 'Laravel Portal');
        $this->fakeAiResume($seed, [
            'technical_skills' => ['Laravel', 'PHP', 'MySQL'],
            'projects' => [[
                'source_id' => $seed['project']->id,
                'name' => 'Laravel Portal',
                'project_type' => 'Web Application',
                'technologies' => ['Laravel', 'PHP', 'MySQL'],
                'bullets' => ['Built Laravel APIs'],
            ]],
        ]);

        $this->actingAs($seed['user'])
            ->post(route('job-analyzer.resume-draft.generate', $seed['analysis']))
            ->assertRedirect();

        $draft = ResumeDraft::query()->firstOrFail();
        $this->assertSame(ResumeDraft::STATUS_COMPLETED, $draft->generation_status);
        $this->assertSame('Alex Developer', $draft->structured_content['contact']['full_name']);
        $this->assertContains('Laravel', $draft->structured_content['technical_skills']);
        $this->assertSame($seed['project']->id, $draft->structured_content['projects'][0]['source_id']);
        $this->assertSame('Acme Corp', $draft->structured_content['experience'][0]['company_name']);

        $this->actingAs($seed['user'])
            ->get(route('resume-drafts.show', $draft))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ResumeDrafts/Show')
                ->where('draft.content.projects.0.name', 'Laravel Portal')
                ->where('draft.target.company_name', 'Hiring Co')
                ->missing('draft.content.ats_score')
            );
    }

    public function test_react_native_and_mern_generation_use_selected_stack(): void
    {
        $this->configureGrok();

        $rn = $this->seedUserWithAnalysis(['React Native', 'JavaScript'], ['React Native', 'Expo'], 'Mobile Courier');
        $this->fakeAiResume($rn, [
            'technical_skills' => ['React Native', 'JavaScript'],
            'projects' => [[
                'source_id' => $rn['project']->id,
                'name' => 'Mobile Courier',
                'project_type' => 'Mobile Application',
                'technologies' => ['React Native', 'Expo'],
                'bullets' => ['Shipped mobile UI flows'],
            ]],
        ]);
        $this->actingAs($rn['user'])->post(route('job-analyzer.resume-draft.generate', $rn['analysis']))->assertRedirect();
        $this->assertSame('Mobile Courier', ResumeDraft::query()->first()->structured_content['projects'][0]['name']);

        ResumeDraft::query()->delete();
        JobAnalysis::query()->delete();
        Project::query()->delete();

        $mern = $this->seedUserWithAnalysis(
            ['MongoDB', 'Express.js', 'React.js', 'Node.js'],
            ['MongoDB', 'Express.js', 'React.js', 'Node.js'],
            'MERN Shop'
        );
        $this->fakeAiResume($mern, [
            'technical_skills' => ['MongoDB', 'Express.js', 'React.js', 'Node.js'],
            'projects' => [[
                'source_id' => $mern['project']->id,
                'name' => 'MERN Shop',
                'project_type' => 'Web Application',
                'technologies' => ['MongoDB', 'Express.js', 'React.js', 'Node.js'],
                'bullets' => ['Built full-stack commerce flows'],
            ]],
        ]);
        $this->actingAs($mern['user'])->post(route('job-analyzer.resume-draft.generate', $mern['analysis']))->assertRedirect();
        $skills = ResumeDraft::query()->first()->structured_content['technical_skills'];
        $this->assertContains('MongoDB', $skills);
        $this->assertContains('React.js', $skills);
    }

    public function test_ignored_and_pending_skills_are_excluded_resume_only_included(): void
    {
        $this->configureGrok();
        $seed = $this->seedUserWithAnalysis(['Laravel', 'PHP', 'Redis', 'Docker'], ['Laravel', 'PHP'], 'API');

        JobAnalysisSkillDecision::query()->create([
            'user_id' => $seed['user']->id,
            'job_analysis_id' => $seed['analysis']->id,
            'canonical_skill_name' => 'Redis',
            'normalized_skill_key' => 'redis',
            'requirement_type' => 'required',
            'decision' => JobAnalysisSkillDecision::DECISION_RESUME_ONLY,
            'confirmation_status' => JobAnalysisSkillDecision::CONFIRMATION_USER_CONFIRMED,
            'decided_at' => now(),
        ]);
        JobAnalysisSkillDecision::query()->create([
            'user_id' => $seed['user']->id,
            'job_analysis_id' => $seed['analysis']->id,
            'canonical_skill_name' => 'Docker',
            'normalized_skill_key' => 'docker',
            'requirement_type' => 'required',
            'decision' => JobAnalysisSkillDecision::DECISION_IGNORE,
            'confirmation_status' => JobAnalysisSkillDecision::CONFIRMATION_NOT_REQUIRED,
            'decided_at' => now(),
        ]);

        $this->fakeAiResume($seed, [
            'technical_skills' => ['Laravel', 'PHP', 'Redis', 'Docker', 'Kubernetes'],
        ]);

        $this->actingAs($seed['user'])
            ->post(route('job-analyzer.resume-draft.generate', $seed['analysis']))
            ->assertRedirect();

        $skills = ResumeDraft::query()->first()->structured_content['technical_skills'];
        $this->assertContains('Laravel', $skills);
        $this->assertContains('Redis', $skills);
        $this->assertNotContains('Docker', $skills);
        $this->assertNotContains('Kubernetes', $skills);
    }

    public function test_unselected_project_is_excluded_and_ownership_enforced(): void
    {
        $this->configureGrok();
        $seed = $this->seedUserWithAnalysis(['Laravel'], ['Laravel'], 'Selected');
        $other = Project::query()->create([
            'user_id' => $seed['user']->id,
            'name' => 'Unselected',
            'short_description' => 'Should not appear',
            'detailed_description' => 'Should not appear',
            'project_type' => 'Web Application',
            'is_active' => true,
            'include_in_resume' => true,
        ]);

        $this->fakeAiResume($seed, [
            'projects' => [
                [
                    'source_id' => $seed['project']->id,
                    'name' => 'Selected',
                    'project_type' => 'Web Application',
                    'technologies' => ['Laravel'],
                    'bullets' => ['Selected project work'],
                ],
                [
                    'source_id' => $other->id,
                    'name' => 'Unselected',
                    'project_type' => 'Web Application',
                    'technologies' => ['Laravel'],
                    'bullets' => ['Should be dropped'],
                ],
            ],
        ]);

        $this->actingAs($seed['user'])
            ->post(route('job-analyzer.resume-draft.generate', $seed['analysis']))
            ->assertRedirect();

        $projects = ResumeDraft::query()->first()->structured_content['projects'];
        $this->assertCount(1, $projects);
        $this->assertSame('Selected', $projects[0]['name']);

        $intruder = User::factory()->create();
        $this->actingAs($intruder)
            ->post(route('job-analyzer.resume-draft.generate', $seed['analysis']))
            ->assertNotFound();
    }

    public function test_unsupported_numbers_are_stripped_and_dates_stay_grounded(): void
    {
        $this->configureGrok();
        $seed = $this->seedUserWithAnalysis(['Laravel'], ['Laravel']);
        $this->fakeAiResume($seed, [
            'experience' => [[
                'source_id' => $seed['experience']->id,
                'company_name' => 'Fake Company',
                'job_title' => 'CTO',
                'employment_type' => 'Full-time',
                'location' => 'Remote',
                'start_date' => '1999-01-01',
                'end_date' => null,
                'is_current' => true,
                'bullets' => ['Increased revenue by 400%', 'Built APIs with the approved stack'],
            ]],
        ]);

        $this->actingAs($seed['user'])
            ->post(route('job-analyzer.resume-draft.generate', $seed['analysis']))
            ->assertRedirect();

        $experience = ResumeDraft::query()->first()->structured_content['experience'][0];
        $this->assertSame('Acme Corp', $experience['company_name']);
        $this->assertSame('Software Engineer', $experience['job_title']);
        $this->assertSame('2022-01-01', $experience['start_date']);
        $this->assertNotContains('Increased revenue by 400%', $experience['bullets']);
        $this->assertContains('Built APIs with the approved stack', $experience['bullets']);
    }

    public function test_invalid_ai_json_is_handled_safely(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $this->configureGrok();
        $seed = $this->seedUserWithAnalysis(['Laravel'], ['Laravel']);

        Http::fake(['api.x.ai/*' => Http::response(['choices' => [['message' => ['content' => '{bad']]]], 200)]);
        RateLimiter::clear('resume-draft:'.$seed['user']->id);

        $this->actingAs($seed['user'])
            ->from(route('job-analyzer.show', $seed['analysis']))
            ->post(route('job-analyzer.resume-draft.generate', $seed['analysis']))
            ->assertSessionHasErrors('resume');

        $this->assertSame(ResumeDraft::STATUS_FAILED, ResumeDraft::query()->first()->generation_status);
    }

    public function test_ai_timeout_is_handled_safely(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $this->configureGrok();
        $seed = $this->seedUserWithAnalysis(['Laravel'], ['Laravel']);

        Http::fake([
            'api.x.ai/*' => Http::failedConnection('Connection timed out'),
        ]);
        RateLimiter::clear('resume-draft:'.$seed['user']->id);

        $this->actingAs($seed['user'])
            ->from(route('job-analyzer.show', $seed['analysis']))
            ->post(route('job-analyzer.resume-draft.generate', $seed['analysis']))
            ->assertSessionHasErrors('resume');
    }

    public function test_regeneration_requires_confirmation(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $this->configureGrok();
        $seed = $this->seedUserWithAnalysis(['Laravel'], ['Laravel']);
        $this->fakeAiResume($seed);
        RateLimiter::clear('resume-draft:'.$seed['user']->id);

        $this->actingAs($seed['user'])
            ->post(route('job-analyzer.resume-draft.generate', $seed['analysis']))
            ->assertRedirect();

        $draft = ResumeDraft::query()->firstOrFail();
        $this->assertTrue($draft->isCompleted());
        $originalUpdatedAt = $draft->updated_at?->toDateTimeString();

        RateLimiter::clear('resume-draft:'.$seed['user']->id);
        $this->actingAs($seed['user'])
            ->from(route('job-analyzer.show', $seed['analysis']))
            ->post(route('job-analyzer.resume-draft.generate', $seed['analysis']), [
                'confirm_replace' => 0,
            ])
            ->assertSessionHasErrors('confirm_replace');

        $this->assertSame(
            $originalUpdatedAt,
            $draft->fresh()->updated_at?->toDateTimeString(),
            'Draft must not change when replacement is not confirmed.'
        );

        RateLimiter::clear('resume-draft:'.$seed['user']->id);

        $replacementPayload = [
            'contact' => [
                'full_name' => 'Alex Developer',
                'headline' => 'Software Engineer',
                'email' => 'alex@example.com',
                'phone' => '555-0100',
                'location' => 'Remote',
                'linkedin_url' => null,
                'github_url' => null,
                'portfolio_url' => null,
            ],
            'professional_summary' => 'Regenerated summary for the target role.',
            'technical_skills' => ['Laravel'],
            'experience' => [[
                'source_id' => $seed['experience']->id,
                'company_name' => 'Acme Corp',
                'job_title' => 'Software Engineer',
                'employment_type' => 'Full-time',
                'location' => 'Remote',
                'start_date' => '2022-01-01',
                'end_date' => null,
                'is_current' => true,
                'bullets' => ['Built APIs with the approved stack'],
            ]],
            'projects' => [[
                'source_id' => $seed['project']->id,
                'name' => $seed['project']->name,
                'project_type' => 'Web Application',
                'technologies' => ['Laravel'],
                'bullets' => ['Delivered relevant project features'],
            ]],
            'education' => [[
                'source_id' => $seed['education']->id,
                'institution_name' => 'State University',
                'degree' => 'B.S.',
                'field_of_study' => 'Computer Science',
                'start_year' => 2016,
                'end_year' => 2020,
                'grade' => null,
            ]],
        ];

        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake([
            'api.x.ai/*' => Http::response([
                'id' => 'chatcmpl-resume-2',
                'choices' => [[
                    'message' => ['content' => json_encode($replacementPayload)],
                ]],
                'usage' => ['total_tokens' => 21],
            ], 200),
        ]);

        $this->actingAs($seed['user'])
            ->post(route('job-analyzer.resume-draft.generate', $seed['analysis']), [
                'confirm_replace' => 1,
            ])
            ->assertRedirect(route('resume-drafts.show', $draft));

        $fresh = $draft->fresh();
        $this->assertTrue($fresh->isCompleted());
        $this->assertSame(
            'Regenerated summary for the target role.',
            $fresh->structured_content['professional_summary']
        );
    }

    public function test_empty_profile_blocks_generation(): void
    {
        $emptyUser = User::factory()->create();
        $emptyAnalysis = JobAnalysis::query()->create([
            'user_id' => $emptyUser->id,
            'job_description' => str_repeat('Incomplete profile analysis content. ', 4),
            'status' => JobAnalysis::STATUS_COMPLETED,
            'analysis_json' => [
                'job_title' => 'Dev',
                'job_category' => null,
                'required_skills' => ['Laravel'],
                'preferred_skills' => [],
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

        $this->actingAs($emptyUser)
            ->from(route('job-analyzer.show', $emptyAnalysis))
            ->post(route('job-analyzer.resume-draft.generate', $emptyAnalysis))
            ->assertSessionHasErrors('resume');
    }
}
