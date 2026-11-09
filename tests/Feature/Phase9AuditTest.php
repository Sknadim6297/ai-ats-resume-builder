<?php

namespace Tests\Feature;

use App\Models\Education;
use App\Models\JobAnalysis;
use App\Models\JobAnalysisProjectSelection;
use App\Models\Profile;
use App\Models\Project;
use App\Models\ProjectTechnology;
use App\Models\ResumeDraft;
use App\Models\ResumeVersion;
use App\Models\Skill;
use App\Models\User;
use App\Models\WorkExperience;
use App\Services\Dashboard\DashboardStatsService;
use App\Services\Resume\GroundedResumeSchema;
use App\Services\Resume\ResumeDocumentNormalizer;
use App\Services\Resume\ResumeDocxExporter;
use App\Services\Resume\ResumePdfExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;
use ZipArchive;

class Phase9AuditTest extends TestCase
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
     * @return array{user: User, analysis: JobAnalysis, experience: WorkExperience, project: Project}
     */
    private function seedCompletedDraft(): array
    {
        $user = User::factory()->create();
        $user->profiles()->where('is_default', true)->firstOrFail()->update([
            'name' => 'Default Profile',
            'full_name' => 'Alex Developer',
            'headline' => 'Software Engineer',
            'email' => 'alex@example.com',
            'phone' => '555-0100',
            'location' => 'Remote',
            'professional_summary' => 'Experienced engineer.',
            'years_of_experience' => 4,
        ]);

        $this->actingAs($user)->post(route('skills.store'), [
            'name' => 'Laravel',
            'category' => 'Backend',
        ]);

        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'Core App',
            'short_description' => 'Relevant project',
            'detailed_description' => 'Built with Laravel.',
            'project_type' => 'Web Application',
            'is_active' => true,
            'include_in_resume' => true,
        ]);
        ProjectTechnology::query()->create([
            'project_id' => $project->id,
            'name' => 'Laravel',
            'sort_order' => 0,
        ]);

        $experience = WorkExperience::query()->create([
            'user_id' => $user->id,
            'company_name' => 'Acme Corp',
            'job_title' => 'Software Engineer',
            'employment_type' => 'Full-time',
            'location' => 'Remote',
            'start_date' => '2022-01-01',
            'end_date' => null,
            'is_current' => true,
            'responsibilities' => ['Built APIs'],
            'achievements' => [],
        ]);

        Education::query()->create([
            'user_id' => $user->id,
            'institution_name' => 'State University',
            'degree' => 'B.S.',
            'field_of_study' => 'Computer Science',
            'start_year' => 2016,
            'end_year' => 2020,
        ]);

        $analysis = JobAnalysis::query()->create([
            'user_id' => $user->id,
            'job_title' => 'Laravel Developer',
            'company_name' => 'Target Co',
            'job_description' => str_repeat('Laravel PHP developer with REST experience. ', 5),
            'status' => JobAnalysis::STATUS_COMPLETED,
            'analysis_json' => [
                'job_title' => 'Laravel Developer',
                'required_skills' => ['Laravel'],
                'preferred_skills' => [],
            ],
            'provider' => 'grok',
            'model' => 'test-model',
        ]);

        JobAnalysisProjectSelection::query()->create([
            'user_id' => $user->id,
            'job_analysis_id' => $analysis->id,
            'project_id' => $project->id,
            'selected' => true,
            'recommended' => true,
            'display_order' => 1,
            'relevance_score' => 90,
        ]);

        $content = [
            'schema_version' => '1.0',
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
            'professional_summary' => 'Grounded summary for Alex Developer.',
            'technical_skills' => ['Laravel'],
            'experience' => [[
                'source_id' => $experience->id,
                'company_name' => 'Acme Corp',
                'job_title' => 'Software Engineer',
                'employment_type' => 'Full-time',
                'location' => 'Remote',
                'start_date' => '2022-01-01',
                'end_date' => null,
                'is_current' => true,
                'bullets' => ['Built APIs'],
            ]],
            'projects' => [[
                'source_id' => $project->id,
                'name' => 'Core App',
                'project_type' => 'Web Application',
                'technologies' => ['Laravel'],
                'bullets' => ['Built with Laravel.'],
            ]],
            'education' => [[
                'source_id' => Education::query()->where('user_id', $user->id)->value('id'),
                'institution_name' => 'State University',
                'degree' => 'B.S.',
                'field_of_study' => 'Computer Science',
                'start_year' => 2016,
                'end_year' => 2020,
                'grade' => null,
            ]],
        ];

        $draft = ResumeDraft::query()->create([
            'user_id' => $user->id,
            'job_analysis_id' => $analysis->id,
            'template_version' => ResumeDraft::TEMPLATE_VERSION,
            'resume_schema_version' => ResumeDraft::SCHEMA_VERSION,
            'structured_content' => $content,
            'source_snapshot' => [
                'contact' => $content['contact'],
                'approved_skills' => ['Laravel'],
                'experiences' => $content['experience'],
                'projects' => $content['projects'],
                'education' => $content['education'],
                'excluded' => ['ignored_skills' => [], 'pending_skills' => []],
                'professional_summary_source' => 'Experienced engineer.',
                'years_of_experience' => 4,
                'job_target' => ['job_title' => 'Laravel Developer'],
            ],
            'generation_status' => ResumeDraft::STATUS_COMPLETED,
            'provider' => 'grok',
            'model' => 'test-model',
            'current_version_number' => 1,
        ]);

        $version = ResumeVersion::query()->create([
            'user_id' => $user->id,
            'resume_draft_id' => $draft->id,
            'job_analysis_id' => $analysis->id,
            'version_number' => 1,
            'change_type' => ResumeVersion::CHANGE_AI_GENERATED,
            'template_version' => ResumeDraft::TEMPLATE_VERSION,
            'resume_schema_version' => ResumeDraft::SCHEMA_VERSION,
            'structured_content' => $content,
            'source_snapshot' => $draft->source_snapshot,
            'is_current' => true,
        ]);

        $draft->update(['current_version_id' => $version->id]);

        return compact('user', 'analysis', 'experience', 'project') + ['draft' => $draft->fresh()];
    }

    public function test_failed_regeneration_preserves_completed_content(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $this->configureGrok();
        $seed = $this->seedCompletedDraft();

        Http::fake([
            'api.x.ai/*' => Http::failedConnection('Connection timed out'),
        ]);
        RateLimiter::clear('resume-draft:'.$seed['user']->id);

        $this->actingAs($seed['user'])
            ->from(route('job-analyzer.show', $seed['analysis']))
            ->post(route('job-analyzer.resume-draft.generate', $seed['analysis']), [
                'confirm_replace' => 1,
            ])
            ->assertSessionHasErrors('resume');

        $draft = $seed['draft']->fresh();
        $this->assertSame(ResumeDraft::STATUS_COMPLETED, $draft->generation_status);
        $this->assertSame('Grounded summary for Alex Developer.', $draft->structured_content['professional_summary']);
        $this->assertNotNull($draft->safe_error);
        $this->assertSame(1, ResumeVersion::query()->where('resume_draft_id', $draft->id)->count());
    }

    public function test_shared_skill_rename_does_not_mutate_other_profiles(): void
    {
        $owner = User::factory()->create();
        $peer = User::factory()->create();

        $this->actingAs($owner)->post(route('skills.store'), [
            'name' => 'Laravel',
            'category' => 'Backend',
            'proficiency' => 5,
        ])->assertRedirect();

        $this->actingAs($peer)->post(route('skills.store'), [
            'name' => 'Laravel',
            'category' => 'Backend',
            'proficiency' => 3,
        ])->assertRedirect();

        $shared = Skill::query()->where('slug', 'laravel')->firstOrFail();
        $this->assertSame(2, DB::table('profile_skills')->where('skill_id', $shared->id)->count());

        $this->actingAs($owner)
            ->put(route('skills.update', $shared->id), [
                'name' => 'Laravel Framework',
                'category' => 'Backend',
                'proficiency' => 5,
                'is_active' => true,
            ])
            ->assertRedirect(route('skills.index'));

        $this->assertDatabaseHas('skills', [
            'id' => $shared->id,
            'slug' => 'laravel',
            'name' => 'Laravel',
        ]);

        $peerSkill = $peer->fresh()->profile->skills()->where('skills.id', $shared->id)->first();
        $this->assertNotNull($peerSkill);
        $this->assertSame('Laravel', $peerSkill->name);

        $ownerSkill = $owner->fresh()->profile->skills()->first();
        $this->assertSame('laravel-framework', $ownerSkill->slug);
        $this->assertSame('Laravel Framework', $ownerSkill->name);
    }

    public function test_schema_and_normalizer_strip_html_markup(): void
    {
        $schema = app(GroundedResumeSchema::class);
        $validated = $schema->validate([
            'contact' => [
                'full_name' => '<b>Alex</b> Developer',
                'headline' => '<script>alert(1)</script>Engineer',
                'email' => 'alex@example.com',
                'phone' => null,
                'location' => null,
                'linkedin_url' => null,
                'github_url' => null,
                'portfolio_url' => null,
            ],
            'professional_summary' => 'Summary with <img src=x onerror=alert(1)> markup.',
            'technical_skills' => ['<i>Laravel</i>'],
            'experience' => [],
            'projects' => [],
            'education' => [],
        ]);

        $this->assertSame('Alex Developer', $validated['contact']['full_name']);
        $this->assertSame('Engineer', $validated['contact']['headline']);
        $this->assertSame('Summary with  markup.', $validated['professional_summary']);
        $this->assertSame(['Laravel'], $validated['technical_skills']);

        $normalized = app(ResumeDocumentNormalizer::class)->normalize($validated);
        $this->assertStringNotContainsString('<', $normalized['professional_summary']);
        $this->assertStringNotContainsString('script', strtolower($normalized['contact']['headline'] ?? ''));
    }

    public function test_failed_ai_attempt_consumes_application_rate_budget(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $this->configureGrok();
        config(['ai.resume_drafting.rate_limit_per_minute' => 1]);

        $seed = $this->seedCompletedDraft();
        Http::fake(['api.x.ai/*' => Http::failedConnection('Connection timed out')]);
        RateLimiter::clear('resume-draft:'.$seed['user']->id);

        $this->actingAs($seed['user'])
            ->from(route('job-analyzer.show', $seed['analysis']))
            ->post(route('job-analyzer.resume-draft.generate', $seed['analysis']), [
                'confirm_replace' => 1,
            ])
            ->assertSessionHasErrors('resume');

        $this->actingAs($seed['user'])
            ->from(route('job-analyzer.show', $seed['analysis']))
            ->post(route('job-analyzer.resume-draft.generate', $seed['analysis']), [
                'confirm_replace' => 1,
            ])
            ->assertSessionHasErrors('resume');

        $errors = session('errors')->getBag('default')->get('resume');
        $this->assertTrue(
            collect($errors)->contains(fn ($msg) => str_contains(strtolower($msg), 'too many')),
            'Second failed attempt should be blocked by application rate limiter.'
        );
    }

    public function test_version_download_rejects_cross_draft_idor(): void
    {
        $a = $this->seedCompletedDraft();
        $b = $this->seedCompletedDraft();

        $this->actingAs($a['user'])
            ->get(route('resume-drafts.download.pdf', [
                'resumeDraft' => $a['draft']->id,
                'resumeVersion' => $b['draft']->current_version_id,
            ]))
            ->assertNotFound();

        $this->actingAs($a['user'])
            ->get(route('resume-drafts.download.docx', [
                'resumeDraft' => $a['draft']->id,
                'resumeVersion' => $b['draft']->current_version_id,
            ]))
            ->assertNotFound();
    }

    public function test_long_and_unicode_exports_remain_valid(): void
    {
        $bullets = [];
        for ($i = 0; $i < 12; $i++) {
            $bullets[] = 'Achievement '.$i.' with detail about scalable systems and measurable delivery outcomes.';
        }

        $content = [
            'contact' => [
                'full_name' => 'José São Paulo',
                'headline' => 'Engenheiro de Software',
                'email' => 'jose@example.com',
                'phone' => '+55 11 99999-0000',
                'location' => 'São Paulo, BR',
                'linkedin_url' => 'https://linkedin.com/in/'.str_repeat('jose-long-path-', 8),
                'github_url' => null,
                'portfolio_url' => null,
            ],
            'professional_summary' => 'Unicode and long-content export verification for José.',
            'technical_skills' => ['Laravel', 'PHP', 'MySQL', 'Redis', 'Docker'],
            'experience' => [[
                'source_id' => 1,
                'company_name' => 'Acme Corp',
                'job_title' => 'Software Engineer',
                'employment_type' => 'Full-time',
                'location' => 'Remote',
                'start_date' => '2018-01-01',
                'end_date' => null,
                'is_current' => true,
                'bullets' => $bullets,
            ]],
            'projects' => [
                [
                    'source_id' => 1,
                    'name' => 'Project Alpha',
                    'project_type' => 'Web Application',
                    'technologies' => ['Laravel'],
                    'bullets' => ['Delivered modular APIs.'],
                ],
                [
                    'source_id' => 2,
                    'name' => 'Project Beta',
                    'project_type' => 'Mobile Application',
                    'technologies' => ['React Native'],
                    'bullets' => ['Shipped offline-first sync.'],
                ],
            ],
            'education' => [],
        ];

        $pdf = app(ResumePdfExporter::class)->render($content);
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertGreaterThan(1500, strlen($pdf));

        $tmp = tempnam(sys_get_temp_dir(), 'docx');
        app(ResumeDocxExporter::class)->renderToPath($content, $tmp);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($tmp));
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        @unlink($tmp);

        $this->assertIsString($xml);
        $this->assertStringContainsString('José São Paulo', $xml);
        $this->assertStringContainsString('Project Alpha', $xml);
        $this->assertStringContainsString('Project Beta', $xml);
    }

    public function test_isolated_query_benchmarks_with_scaled_seed_data(): void
    {
        $user = User::factory()->create();
        $user->profiles()->where('is_default', true)->firstOrFail()->update([
            'full_name' => 'Bench User',
            'email' => 'bench@example.com',
        ]);
        $profileId = $user->fresh()->profile->id;

        $skillRows = [];
        for ($i = 1; $i <= 1000; $i++) {
            $skillRows[] = [
                'name' => 'Skill '.$i,
                'slug' => 'skill-'.$i,
                'category' => 'Backend',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        foreach (array_chunk($skillRows, 200) as $chunk) {
            DB::table('skills')->insert($chunk);
        }

        $skillIds = DB::table('skills')->orderBy('id')->pluck('id');
        $pivot = [];
        foreach ($skillIds as $index => $skillId) {
            $pivot[] = [
                'profile_id' => $profileId,
                'skill_id' => $skillId,
                'proficiency' => 3,
                'is_primary' => false,
                'is_verified' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if (count($pivot) === 200) {
                DB::table('profile_skills')->insert($pivot);
                $pivot = [];
            }
        }
        if ($pivot !== []) {
            DB::table('profile_skills')->insert($pivot);
        }

        $projectRows = [];
        for ($i = 1; $i <= 2000; $i++) {
            $projectRows[] = [
                'user_id' => $user->id,
                'name' => 'Project '.$i,
                'short_description' => 'Short '.$i,
                'detailed_description' => 'Detailed '.$i,
                'project_type' => 'Web Application',
                'is_active' => true,
                'include_in_resume' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if (count($projectRows) === 200) {
                DB::table('projects')->insert($projectRows);
                $projectRows = [];
            }
        }
        if ($projectRows !== []) {
            DB::table('projects')->insert($projectRows);
        }

        $analysisRows = [];
        for ($i = 1; $i <= 2000; $i++) {
            $analysisRows[] = [
                'user_id' => $user->id,
                'job_title' => 'Role '.$i,
                'company_name' => 'Company '.$i,
                'job_description' => str_repeat('Requirements for role '.$i.'. ', 10),
                'status' => JobAnalysis::STATUS_COMPLETED,
                'analysis_json' => json_encode(['required_skills' => ['Skill 1'], 'preferred_skills' => []]),
                'provider' => 'grok',
                'model' => 'test-model',
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if (count($analysisRows) === 200) {
                DB::table('job_analyses')->insert($analysisRows);
                $analysisRows = [];
            }
        }
        if ($analysisRows !== []) {
            DB::table('job_analyses')->insert($analysisRows);
        }

        $analysisIds = DB::table('job_analyses')->where('user_id', $user->id)->orderBy('id')->pluck('id');
        $draftRows = [];
        foreach ($analysisIds->take(1000) as $index => $analysisId) {
            $draftRows[] = [
                'user_id' => $user->id,
                'job_analysis_id' => $analysisId,
                'template_version' => ResumeDraft::TEMPLATE_VERSION,
                'resume_schema_version' => ResumeDraft::SCHEMA_VERSION,
                'structured_content' => json_encode(['professional_summary' => 'Summary '.$index]),
                'source_snapshot' => json_encode(['approved_skills' => ['Skill 1']]),
                'generation_status' => ResumeDraft::STATUS_COMPLETED,
                'provider' => 'grok',
                'model' => 'test-model',
                'current_version_number' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if (count($draftRows) === 100) {
                DB::table('resume_drafts')->insert($draftRows);
                $draftRows = [];
            }
        }
        if ($draftRows !== []) {
            DB::table('resume_drafts')->insert($draftRows);
        }

        $draftIds = DB::table('resume_drafts')->where('user_id', $user->id)->orderBy('id')->get(['id', 'job_analysis_id']);
        $versionRows = [];
        foreach ($draftIds as $draft) {
            for ($v = 1; $v <= 5; $v++) {
                $versionRows[] = [
                    'user_id' => $user->id,
                    'resume_draft_id' => $draft->id,
                    'job_analysis_id' => $draft->job_analysis_id,
                    'version_number' => $v,
                    'change_type' => ResumeVersion::CHANGE_AI_GENERATED,
                    'template_version' => ResumeDraft::TEMPLATE_VERSION,
                    'resume_schema_version' => ResumeDraft::SCHEMA_VERSION,
                    'structured_content' => json_encode(['professional_summary' => 'v'.$v]),
                    'source_snapshot' => json_encode(['approved_skills' => ['Skill 1']]),
                    'is_current' => $v === 5,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            if (count($versionRows) >= 500) {
                DB::table('resume_versions')->insert($versionRows);
                $versionRows = [];
            }
        }
        if ($versionRows !== []) {
            DB::table('resume_versions')->insert($versionRows);
        }

        $this->assertSame(1000, DB::table('skills')->count());
        $this->assertSame(2000, DB::table('projects')->where('user_id', $user->id)->count());
        $this->assertSame(2000, DB::table('job_analyses')->where('user_id', $user->id)->count());
        $this->assertSame(5000, DB::table('resume_versions')->where('user_id', $user->id)->count());

        $start = hrtime(true);
        $skillsPage = $user->fresh()->profile->skills()->orderBy('skills.name')->paginate(12);
        $skillsMs = (hrtime(true) - $start) / 1e6;
        $this->assertSame(12, $skillsPage->count());

        $start = hrtime(true);
        $history = ResumeDraft::query()
            ->where('user_id', $user->id)
            ->where('generation_status', ResumeDraft::STATUS_COMPLETED)
            ->latest('updated_at')
            ->paginate(10);
        $historyMs = (hrtime(true) - $start) / 1e6;
        $this->assertSame(10, $history->count());

        $start = hrtime(true);
        $stats = app(DashboardStatsService::class)->forUser($user->fresh());
        $dashboardMs = (hrtime(true) - $start) / 1e6;
        $this->assertGreaterThan(0, $stats['skills']);

        $start = hrtime(true);
        $activeProjects = Project::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->where('include_in_resume', true)
            ->orderBy('name')
            ->limit(50)
            ->get();
        $projectMs = (hrtime(true) - $start) / 1e6;
        $this->assertCount(50, $activeProjects);

        // Persist measurements for the Phase 9 report (test DB only; RefreshDatabase rolls back).
        file_put_contents(
            storage_path('logs/phase9-perf.json'),
            json_encode([
                'seed' => [
                    'skills' => 1000,
                    'projects' => 2000,
                    'job_analyses' => 2000,
                    'resume_versions' => 5000,
                ],
                'query_ms' => [
                    'skills_paginate_12' => round($skillsMs, 2),
                    'resume_history_paginate_10' => round($historyMs, 2),
                    'dashboard_stats' => round($dashboardMs, 2),
                    'active_projects_limit_50' => round($projectMs, 2),
                ],
                'memory_peak_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
            ], JSON_PRETTY_PRINT)
        );

        $this->assertLessThan(2000, $skillsMs, 'Skills pagination unexpectedly slow');
        $this->assertLessThan(2000, $historyMs, 'Resume history pagination unexpectedly slow');
        $this->assertLessThan(3000, $dashboardMs, 'Dashboard aggregates unexpectedly slow');
        $this->assertLessThan(2000, $projectMs, 'Project eligibility query unexpectedly slow');
    }
}
