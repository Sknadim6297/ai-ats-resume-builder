<?php

namespace Tests\Feature;

use App\Models\JobAnalysis;
use App\Models\JobAnalysisSkillDecision;
use App\Models\Project;
use App\Models\ResumeDraft;
use App\Models\ResumeVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Phase 10 end-to-end workflow using isolated :memory: SQLite and mocked Grok.
 * Live paid API calls are intentionally not performed here.
 */
class Phase10E2EWorkflowTest extends TestCase
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

    private function jdPayload(string $title, array $required, array $preferred = []): array
    {
        return [
            'job_title' => $title,
            'job_category' => 'Engineering',
            'required_skills' => $required,
            'preferred_skills' => $preferred,
            'frameworks_libraries' => $required,
            'databases' => array_values(array_filter($required, fn ($s) => in_array($s, ['MySQL', 'MongoDB'], true))),
            'tools_platforms' => [],
            'experience' => ['minimum_years' => 2, 'maximum_years' => 5, 'summary' => '2-5 years'],
            'education_requirements' => [],
            'responsibilities' => ['Build and maintain applications', 'Collaborate with product teams'],
            'technical_keywords' => $required,
            'work_arrangement' => 'Remote',
            'location' => null,
            'notes' => null,
        ];
    }

    private function fakeGrokSequence(array $jdPayload, array $resumePayload): void
    {
        $responses = [
            Http::response([
                'id' => 'chatcmpl-jd',
                'choices' => [['message' => ['content' => json_encode($jdPayload)]]],
                'usage' => ['total_tokens' => 12],
            ], 200),
            Http::response([
                'id' => 'chatcmpl-resume',
                'choices' => [['message' => ['content' => json_encode($resumePayload)]]],
                'usage' => ['total_tokens' => 24],
            ], 200),
        ];

        Http::fake([
            'api.x.ai/*' => function () use (&$responses) {
                return array_shift($responses) ?? Http::response(['error' => 'unexpected extra AI call'], 500);
            },
        ]);
    }

    public function test_complete_authenticated_workflow_login_to_logout(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $this->configureGrok();

        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);

        // 1–2 Login + dashboard
        $this->post(route('login'), [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Dashboard'));

        // 3 Master profile
        $this->actingAs($user)->put(route('master-profile.update'), [
            'full_name' => 'Alex Developer',
            'headline' => 'Software Engineer',
            'email' => 'alex@example.com',
            'phone' => '555-0100',
            'location' => 'Remote',
            'professional_summary' => 'Engineer building reliable Laravel and React applications.',
            'years_of_experience' => 5,
        ])->assertRedirect();

        // 4 Skills
        foreach ([
            ['Laravel', 'Backend'],
            ['PHP', 'Programming Languages'],
            ['React', 'Frontend'],
            ['Node.js', 'Backend'],
            ['MongoDB', 'Databases'],
        ] as [$name, $category]) {
            $this->actingAs($user)->post(route('skills.store'), [
                'name' => $name,
                'category' => $category,
                'proficiency' => 4,
                'is_active' => true,
            ])->assertRedirect(route('skills.index'));
        }

        // 5 Projects
        $this->actingAs($user)->post(route('projects.store'), [
            'name' => 'Laravel Portal',
            'short_description' => 'ATS portal backend',
            'detailed_description' => 'Built APIs with Laravel and MySQL.',
            'project_type' => 'Web Application',
            'is_active' => true,
            'include_in_resume' => true,
            'technologies' => ['Laravel', 'PHP', 'MySQL'],
            'features' => ['Role-based access'],
            'responsibilities' => ['Designed REST endpoints'],
        ])->assertRedirect();

        $this->actingAs($user)->post(route('projects.store'), [
            'name' => 'RN Field App',
            'short_description' => 'Mobile field tooling',
            'detailed_description' => 'Offline-first React Native app.',
            'project_type' => 'Mobile Application',
            'is_active' => true,
            'include_in_resume' => true,
            'technologies' => ['React Native', 'JavaScript'],
            'features' => ['Offline sync'],
            'responsibilities' => ['Implemented navigation'],
        ])->assertRedirect();

        $laravelProject = Project::query()->where('name', 'Laravel Portal')->firstOrFail();
        $rnProject = Project::query()->where('name', 'RN Field App')->firstOrFail();

        // 6 Experience
        $this->actingAs($user)->post(route('experience.store'), [
            'company_name' => 'Acme Corp',
            'job_title' => 'Software Engineer',
            'employment_type' => 'Full-time',
            'location' => 'Remote',
            'start_date' => '2021-01-01',
            'end_date' => null,
            'is_current' => true,
            'responsibilities' => ['Built APIs with Laravel'],
            'achievements' => ['Improved delivery cadence'],
            'technologies' => ['Laravel', 'PHP'],
        ])->assertRedirect();

        // 7 Education
        $this->actingAs($user)->post(route('education.store'), [
            'institution_name' => 'State University',
            'degree' => 'B.S.',
            'field_of_study' => 'Computer Science',
            'start_year' => 2015,
            'end_year' => 2019,
            'grade' => null,
        ])->assertRedirect();

        $experienceId = $user->fresh()->workExperiences()->value('id');
        $educationId = $user->fresh()->educations()->value('id');

        $jd = $this->jdPayload(
            'Laravel Developer',
            ['Laravel', 'PHP', 'MySQL', 'Redis'],
            ['Docker']
        );

        $resumePayload = [
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
            'professional_summary' => 'Software engineer focused on Laravel delivery for the target role.',
            'technical_skills' => ['Laravel', 'PHP', 'MySQL', 'Redis'],
            'experience' => [[
                'source_id' => $experienceId,
                'company_name' => 'Acme Corp',
                'job_title' => 'Software Engineer',
                'employment_type' => 'Full-time',
                'location' => 'Remote',
                'start_date' => '2021-01-01',
                'end_date' => null,
                'is_current' => true,
                'bullets' => ['Built APIs with Laravel'],
            ]],
            'projects' => [[
                'source_id' => $laravelProject->id,
                'name' => 'Laravel Portal',
                'project_type' => 'Web Application',
                'technologies' => ['Laravel', 'PHP', 'MySQL'],
                'bullets' => ['Designed REST endpoints'],
            ]],
            'education' => [[
                'source_id' => $educationId,
                'institution_name' => 'State University',
                'degree' => 'B.S.',
                'field_of_study' => 'Computer Science',
                'start_year' => 2015,
                'end_year' => 2019,
                'grade' => null,
            ]],
        ];

        $this->fakeGrokSequence($jd, $resumePayload);
        RateLimiter::clear('job-analysis:'.$user->id);
        RateLimiter::clear('resume-draft:'.$user->id);

        // 8–10 Analyze JD
        $this->actingAs($user)->post(route('job-analyzer.store'), [
            'job_title' => 'Laravel Developer',
            'company_name' => 'Hiring Co',
            'job_description' => str_repeat(
                'We need a Laravel Developer with PHP, MySQL, Redis experience. Docker is preferred. Build APIs and collaborate with product. ',
                3
            ),
        ])->assertRedirect();

        $analysis = JobAnalysis::query()->firstOrFail();
        $this->assertSame(JobAnalysis::STATUS_COMPLETED, $analysis->status);
        $this->assertContains('Laravel', $analysis->analysis_json['required_skills']);
        $this->assertContains('Redis', $analysis->analysis_json['required_skills']);

        // 11 Skill approvals (profile / resume-only) — no extra AI calls
        $httpBeforeDecisions = Http::recorded();
        $this->actingAs($user)->post(route('job-analyzer.skill-decisions.store', $analysis), [
            'skill_name' => 'Redis',
            'requirement_type' => JobAnalysisSkillDecision::REQUIREMENT_REQUIRED,
            'decision' => JobAnalysisSkillDecision::DECISION_ADD_TO_PROFILE,
            'confirmed' => true,
        ])->assertRedirect();

        $this->actingAs($user)->post(route('job-analyzer.skill-decisions.store', $analysis), [
            'skill_name' => 'Docker',
            'requirement_type' => JobAnalysisSkillDecision::REQUIREMENT_PREFERRED,
            'decision' => JobAnalysisSkillDecision::DECISION_RESUME_ONLY,
            'confirmed' => true,
        ])->assertRedirect();

        $this->assertTrue(
            $user->fresh()->profile->skills()->where('skills.slug', 'redis')->exists()
        );
        $this->assertFalse(
            $user->fresh()->profile->skills()->where('skills.slug', 'docker')->exists(),
            'Resume-only must not mutate master profile'
        );
        $this->assertCount(count($httpBeforeDecisions), Http::recorded(), 'Skill decisions must not call AI');

        // 12–13 Project selection
        $this->actingAs($user)->put(route('job-analyzer.project-selection.update', $analysis), [
            'project_ids' => [$laravelProject->id],
        ])->assertRedirect();

        $this->actingAs($user)->get(route('job-analyzer.show', $analysis))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('JobAnalyzer/Show')
                ->where('analysis.id', $analysis->id)
            );

        // 14–15 Generate + preview
        RateLimiter::clear('resume-draft:'.$user->id);
        $this->actingAs($user)
            ->post(route('job-analyzer.resume-draft.generate', $analysis))
            ->assertRedirect();

        $draft = ResumeDraft::query()->firstOrFail();
        $this->assertSame(ResumeDraft::STATUS_COMPLETED, $draft->generation_status);
        $this->assertSame('Acme Corp', $draft->structured_content['experience'][0]['company_name']);
        $this->assertSame('Laravel Portal', $draft->structured_content['projects'][0]['name']);
        $this->assertNotContains('RN Field App', collect($draft->structured_content['projects'])->pluck('name'));

        $this->actingAs($user)->get(route('resume-drafts.show', $draft))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('ResumeDrafts/Show'));

        // 16–17 Edit + version
        $edited = $draft->structured_content;
        $edited['professional_summary'] = 'Edited summary tailored for Laravel Developer roles.';
        $this->actingAs($user)->put(route('resume-drafts.update', $draft), $edited)
            ->assertRedirect(route('resume-drafts.show', $draft));

        $this->assertSame(2, ResumeVersion::query()->where('resume_draft_id', $draft->id)->count());
        $v1 = ResumeVersion::query()->where('resume_draft_id', $draft->id)->where('version_number', 1)->firstOrFail();
        $this->assertStringContainsString('Software engineer focused on Laravel', $v1->structured_content['professional_summary']);

        // 18 Restore
        $this->actingAs($user)
            ->post(route('resume-drafts.versions.restore', [$draft, $v1]))
            ->assertRedirect(route('resume-drafts.show', $draft));
        $this->assertSame(3, ResumeVersion::query()->where('resume_draft_id', $draft->id)->count());

        // 19–20 Downloads
        $this->actingAs($user)->get(route('resume-drafts.download.pdf', $draft))
            ->assertOk()
            ->assertHeader('content-disposition');
        $this->actingAs($user)->get(route('resume-drafts.download.docx', $draft))
            ->assertOk()
            ->assertHeader('content-disposition');

        // 21 History
        $this->actingAs($user)->get(route('resumes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ResumeDrafts/Index')
                ->has('resumes.data', 1)
            );

        // Cross-user isolation
        $intruder = User::factory()->create();
        $this->actingAs($intruder)->get(route('resume-drafts.show', $draft))->assertNotFound();
        $this->actingAs($intruder)->get(route('resume-drafts.download.pdf', $draft))->assertNotFound();

        // 22 Logout
        $this->actingAs($user)->post(route('logout'))->assertRedirect('/');
        $this->assertGuest();
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('jobCategoryProvider')]
    public function test_job_category_analysis_shapes(string $title, array $required, array $preferred): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $this->configureGrok();
        $user = User::factory()->create();

        Http::fake([
            'api.x.ai/*' => Http::response([
                'id' => 'chatcmpl-jd',
                'choices' => [['message' => ['content' => json_encode($this->jdPayload($title, $required, $preferred))]]],
            ], 200),
        ]);

        RateLimiter::clear('job-analysis:'.$user->id);

        $this->actingAs($user)->post(route('job-analyzer.store'), [
            'job_title' => $title,
            'job_description' => str_repeat("Hiring a {$title}. Required: ".implode(', ', $required).'. Preferred: '.implode(', ', $preferred).'. ', 4),
        ])->assertRedirect();

        $analysis = JobAnalysis::query()->latest('id')->firstOrFail();
        $this->assertSame($title, $analysis->analysis_json['job_title']);
        foreach ($required as $skill) {
            $this->assertContains($skill, $analysis->analysis_json['required_skills']);
        }
        foreach ($preferred as $skill) {
            $this->assertContains($skill, $analysis->analysis_json['preferred_skills']);
        }
    }

    public static function jobCategoryProvider(): array
    {
        // Use post-normalization canonical names expected by SkillNormalizer.
        return [
            'laravel' => ['Laravel Developer', ['Laravel', 'PHP', 'MySQL'], ['Redis']],
            'react_native' => ['React Native Developer', ['React Native', 'JavaScript'], ['TypeScript']],
            'mern' => ['MERN Stack Developer', ['MongoDB', 'Express.js', 'React.js', 'Node.js'], ['TypeScript']],
            'fullstack' => ['Full Stack Developer', ['JavaScript', 'Node.js', 'React.js', 'SQL'], ['AWS']],
        ];
    }
}
