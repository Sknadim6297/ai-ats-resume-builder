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
use App\Models\User;
use App\Models\WorkExperience;
use App\Services\Resume\ResumeDocxExporter;
use App\Services\Resume\ResumePdfExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;
use ZipArchive;

class ResumePhase8Test extends TestCase
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

    private function seedCompletedDraft(): array
    {
        $this->configureGrok();
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
            'name' => 'Portal',
            'short_description' => 'Portal app',
            'detailed_description' => 'Portal details',
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

        $education = Education::query()->create([
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
            'company_name' => 'Hiring Co',
            'job_description' => str_repeat('Need a Laravel developer for APIs and MySQL. ', 4),
            'status' => JobAnalysis::STATUS_COMPLETED,
            'provider' => 'grok',
            'model' => 'test-model',
            'analysis_json' => [
                'job_title' => 'Laravel Developer',
                'job_category' => 'Backend',
                'required_skills' => ['Laravel'],
                'preferred_skills' => [],
                'frameworks_libraries' => ['Laravel'],
                'databases' => [],
                'tools_platforms' => [],
                'experience' => ['minimum_years' => 2, 'maximum_years' => null, 'summary' => null],
                'education_requirements' => [],
                'responsibilities' => ['Build APIs'],
                'technical_keywords' => ['Laravel'],
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
            'selection_source' => 'manual',
        ]);

        $payload = [
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
            'professional_summary' => 'Software engineer focused on Laravel delivery.',
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
                'name' => 'Portal',
                'project_type' => 'Web Application',
                'technologies' => ['Laravel'],
                'bullets' => ['Delivered portal modules'],
            ]],
            'education' => [[
                'source_id' => $education->id,
                'institution_name' => 'State University',
                'degree' => 'B.S.',
                'field_of_study' => 'Computer Science',
                'start_year' => 2016,
                'end_year' => 2020,
                'grade' => null,
            ]],
        ];

        Http::fake([
            'api.x.ai/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode($payload)]]],
            ], 200),
        ]);

        RateLimiter::clear('resume-draft:'.$user->id);
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $this->actingAs($user)->post(route('job-analyzer.resume-draft.generate', $analysis))->assertRedirect();

        $draft = ResumeDraft::query()->firstOrFail();

        return compact('user', 'analysis', 'draft', 'project', 'experience', 'education', 'payload');
    }

    public function test_ai_generation_creates_version_history(): void
    {
        $seed = $this->seedCompletedDraft();

        $this->assertDatabaseCount('resume_versions', 1);
        $this->assertDatabaseHas('resume_versions', [
            'resume_draft_id' => $seed['draft']->id,
            'version_number' => 1,
            'change_type' => ResumeVersion::CHANGE_AI_GENERATED,
            'is_current' => true,
        ]);
        $this->assertSame(1, $seed['draft']->fresh()->current_version_number);
    }

    public function test_manual_edit_creates_new_version_and_rejects_unapproved_skill(): void
    {
        $seed = $this->seedCompletedDraft();
        $content = $seed['draft']->structured_content;

        $content['professional_summary'] = 'Updated summary for Laravel roles.';
        $content['experience'][0]['bullets'] = ['Built grounded Laravel APIs'];

        $this->actingAs($seed['user'])
            ->put(route('resume-drafts.update', $seed['draft']), $content)
            ->assertRedirect(route('resume-drafts.show', $seed['draft']));

        $this->assertDatabaseCount('resume_versions', 2);
        $this->assertDatabaseHas('resume_versions', [
            'version_number' => 2,
            'change_type' => ResumeVersion::CHANGE_MANUALLY_EDITED,
            'is_current' => true,
        ]);
        $this->assertSame(
            'Updated summary for Laravel roles.',
            $seed['draft']->fresh()->structured_content['professional_summary']
        );

        $bad = $content;
        $bad['technical_skills'] = ['Laravel', 'Kubernetes'];
        $this->actingAs($seed['user'])
            ->put(route('resume-drafts.update', $seed['draft']), $bad)
            ->assertRedirect();

        $skills = $seed['draft']->fresh()->structured_content['technical_skills'];
        $this->assertContains('Laravel', $skills);
        $this->assertNotContains('Kubernetes', $skills);
    }

    public function test_unselected_project_and_fake_experience_are_dropped(): void
    {
        $seed = $this->seedCompletedDraft();
        $content = $seed['draft']->structured_content;
        $content['projects'][] = [
            'source_id' => 99999,
            'name' => 'Fake Project',
            'project_type' => 'Web Application',
            'technologies' => ['Laravel'],
            'bullets' => ['Invented work'],
        ];
        $content['experience'][] = [
            'source_id' => 99999,
            'company_name' => 'Fake Co',
            'job_title' => 'CTO',
            'employment_type' => 'Full-time',
            'location' => 'Remote',
            'start_date' => '2020-01-01',
            'end_date' => null,
            'is_current' => true,
            'bullets' => ['Invented leadership'],
        ];

        $this->actingAs($seed['user'])
            ->put(route('resume-drafts.update', $seed['draft']), $content)
            ->assertRedirect();

        $fresh = $seed['draft']->fresh()->structured_content;
        $this->assertCount(1, $fresh['projects']);
        $this->assertSame('Portal', $fresh['projects'][0]['name']);
        $this->assertCount(1, $fresh['experience']);
        $this->assertSame('Acme Corp', $fresh['experience'][0]['company_name']);
    }

    public function test_restore_previous_version_and_ownership_isolation(): void
    {
        $seed = $this->seedCompletedDraft();
        $v1 = ResumeVersion::query()->firstOrFail();

        $content = $seed['draft']->structured_content;
        $content['professional_summary'] = 'Second version summary';
        $this->actingAs($seed['user'])->put(route('resume-drafts.update', $seed['draft']), $content)->assertRedirect();

        $this->actingAs($seed['user'])
            ->post(route('resume-drafts.versions.restore', [$seed['draft'], $v1]))
            ->assertRedirect(route('resume-drafts.show', $seed['draft']));

        $this->assertDatabaseCount('resume_versions', 3);
        $current = ResumeVersion::query()->where('is_current', true)->firstOrFail();
        $this->assertSame(3, $current->version_number);
        $this->assertSame(ResumeVersion::CHANGE_RESTORED, $current->change_type);
        $this->assertSame(
            'Software engineer focused on Laravel delivery.',
            $current->structured_content['professional_summary']
        );

        $intruder = User::factory()->create();
        $this->actingAs($intruder)
            ->get(route('resume-drafts.show', $seed['draft']))
            ->assertNotFound();
        $this->actingAs($intruder)
            ->get(route('resume-drafts.download.pdf', $seed['draft']))
            ->assertNotFound();
    }

    public function test_resume_index_and_preview_pages(): void
    {
        $seed = $this->seedCompletedDraft();

        $this->actingAs($seed['user'])
            ->get(route('resumes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ResumeDrafts/Index')
                ->has('resumes.data', 1)
            );

        $this->actingAs($seed['user'])
            ->get(route('resume-drafts.edit', $seed['draft']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('ResumeDrafts/Edit'));
    }

    public function test_pdf_and_docx_downloads_contain_content(): void
    {
        $seed = $this->seedCompletedDraft();
        $version = ResumeVersion::query()->firstOrFail();

        $pdfResponse = $this->actingAs($seed['user'])
            ->get(route('resume-drafts.download.pdf', [$seed['draft'], $version]));
        $pdfResponse->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdfResponse->headers->get('content-type'));
        $pdfBinary = $pdfResponse->streamedContent();
        $this->assertStringStartsWith('%PDF', $pdfBinary);
        $this->assertGreaterThan(800, strlen($pdfBinary));
        $this->assertStringContainsString(
            'alex-developer-v1.pdf',
            (string) $pdfResponse->headers->get('content-disposition')
        );

        $docxResponse = $this->actingAs($seed['user'])
            ->get(route('resume-drafts.download.docx', [$seed['draft'], $version]));
        $docxResponse->assertOk();
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            (string) $docxResponse->headers->get('content-type')
        );
        $docxBinary = $docxResponse->streamedContent();
        $this->assertNotSame('', $docxBinary);
        $this->assertStringContainsString(
            'alex-developer-v1.docx',
            (string) $docxResponse->headers->get('content-disposition')
        );

        $tmp = tempnam(sys_get_temp_dir(), 'docx');
        file_put_contents($tmp, $docxBinary);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($tmp) === true);
        $this->assertNotFalse($zip->locateName('word/document.xml'));
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        @unlink($tmp);
        $this->assertStringContainsString('Alex Developer', $xml);
        $this->assertStringContainsString('Laravel', $xml);
    }

    public function test_exporters_handle_unicode_and_empty_optional_sections(): void
    {
        $content = [
            'contact' => [
                'full_name' => 'José Núñez',
                'headline' => null,
                'email' => 'jose@example.com',
                'phone' => null,
                'location' => 'São Paulo',
                'linkedin_url' => null,
                'github_url' => null,
                'portfolio_url' => null,
            ],
            'professional_summary' => 'Engenheiro com experiência em APIs.',
            'technical_skills' => ['Laravel', 'PHP'],
            'experience' => [],
            'projects' => [],
            'education' => [],
        ];

        $pdf = app(ResumePdfExporter::class)->render($content);
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertTrue(strlen($pdf) > 500);

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'resume-unicode-'.uniqid().'.docx';
        app(ResumeDocxExporter::class)->renderToPath($content, $path);
        $this->assertFileExists($path);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        @unlink($path);
        $this->assertStringContainsString('José', $xml);
    }

    public function test_regeneration_preserves_prior_versions(): void
    {
        $seed = $this->seedCompletedDraft();
        $this->assertDatabaseCount('resume_versions', 1);

        Http::swap(new \Illuminate\Http\Client\Factory);
        $payload = $seed['payload'];
        $payload['professional_summary'] = 'Fresh AI regeneration summary.';
        Http::fake([
            'api.x.ai/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode($payload)]]],
            ], 200),
        ]);
        RateLimiter::clear('resume-draft:'.$seed['user']->id);

        $this->actingAs($seed['user'])
            ->post(route('job-analyzer.resume-draft.generate', $seed['analysis']), [
                'confirm_replace' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('resume_versions', 2);
        $this->assertSame(2, ResumeVersion::query()->where('is_current', true)->value('version_number'));
        $this->assertTrue(ResumeVersion::query()->where('version_number', 1)->exists());
    }
}
