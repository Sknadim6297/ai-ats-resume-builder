<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\ResumeImport;
use App\Models\User;
use App\Services\ResumeImport\ResumeImportParser;
use App\Services\ResumeImport\ResumeImportSchema;
use App\Services\ResumeImport\ResumeImportService;
use App\Services\ResumeImport\ResumeTextExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class ResumeImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_file_type_is_rejected(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('resume-imports.store'), [
                'resume' => UploadedFile::fake()->create('notes.txt', 100, 'text/plain'),
            ])
            ->assertSessionHasErrors('resume');
    }

    public function test_empty_extraction_fails_clearly(): void
    {
        $extractor = app(ResumeTextExtractor::class);

        $this->expectException(ValidationException::class);

        $path = storage_path('framework/testing-empty.pdf');
        file_put_contents($path, '%PDF-1.4 empty');
        try {
            $extractor->extract($path, 'application/pdf', 'empty.pdf');
        } finally {
            @unlink($path);
        }
    }

    public function test_malformed_ai_payload_is_rejected_by_schema(): void
    {
        $schema = app(ResumeImportSchema::class);

        $this->expectException(ValidationException::class);
        $schema->validate([
            'personal' => [
                'full_name' => ['not', 'a', 'string'],
            ],
        ]);
    }

    public function test_review_required_before_commit(): void
    {
        $user = User::factory()->create();
        $import = ResumeImport::query()->create([
            'user_id' => $user->id,
            'original_filename' => 'cv.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 100,
            'disk' => 'local',
            'status' => ResumeImport::STATUS_UPLOADED,
        ]);

        $this->actingAs($user)
            ->post(route('resume-imports.commit', $import), [
                'merge_strategy' => 'new_profile',
                'new_profile_name' => 'Imported',
            ])
            ->assertSessionHasErrors();
    }

    public function test_commit_as_new_profile_keeps_skills_unverified(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $import = ResumeImport::query()->create([
            'user_id' => $user->id,
            'original_filename' => 'cv.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 100,
            'disk' => 'local',
            'status' => ResumeImport::STATUS_REVIEW,
            'review_payload' => [
                'personal' => [
                    'full_name' => 'Ada Lovelace',
                    'headline' => 'AI Developer',
                    'email' => 'ada@example.com',
                    'phone' => null,
                    'location' => null,
                    'linkedin_url' => null,
                    'github_url' => null,
                    'portfolio_url' => null,
                    'professional_summary' => 'Mathematician',
                    'years_of_experience' => 5,
                    'additional_links' => [],
                ],
                'skills' => ['Laravel', 'PHP'],
                'work_experiences' => [],
                'projects' => [],
                'education' => [],
                'certifications' => [],
                'uncertain_fields' => [],
            ],
        ]);

        $this->actingAs($user)
            ->post(route('resume-imports.commit', $import), [
                'merge_strategy' => 'new_profile',
                'new_profile_name' => 'AI Developer',
            ])
            ->assertRedirect();

        $profile = Profile::query()->where('user_id', $user->id)->where('name', 'AI Developer')->first();
        $this->assertNotNull($profile);

        $pivot = $profile->skills()->where('skills.slug', 'laravel')->first();
        $this->assertNotNull($pivot);
        $this->assertFalse((bool) $pivot->pivot->is_verified);
        $this->assertSame('imported', $pivot->pivot->source);
    }

    public function test_merge_skips_duplicate_experience(): void
    {
        $user = User::factory()->create();
        $profile = $user->profiles()->first();

        $profile->workExperiences()->create([
            'user_id' => $user->id,
            'company_name' => 'Acme',
            'job_title' => 'Engineer',
            'employment_type' => 'Full-time',
            'start_date' => '2020-01-01',
            'is_current' => false,
            'responsibilities' => [],
            'achievements' => [],
        ]);

        $import = ResumeImport::query()->create([
            'user_id' => $user->id,
            'original_filename' => 'cv.pdf',
            'status' => ResumeImport::STATUS_REVIEW,
            'review_payload' => [
                'personal' => [
                    'full_name' => $user->name,
                    'headline' => $profile->headline,
                    'email' => $user->email,
                    'professional_summary' => null,
                    'additional_links' => [],
                ],
                'skills' => [],
                'work_experiences' => [[
                    'company_name' => 'Acme',
                    'job_title' => 'Engineer',
                    'employment_type' => 'Full-time',
                    'start_date' => '2020-01-01',
                    'is_current' => false,
                    'responsibilities' => [],
                    'achievements' => [],
                    'technologies' => [],
                ]],
                'projects' => [],
                'education' => [],
                'certifications' => [],
                'uncertain_fields' => [],
            ],
        ]);

        $this->actingAs($user)
            ->post(route('resume-imports.commit', $import), [
                'merge_strategy' => 'merge',
                'profile_id' => $profile->id,
            ])
            ->assertRedirect();

        $this->assertSame(1, $profile->fresh()->workExperiences()->count());
    }

    public function test_cancel_import_is_safe(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $path = 'resume-imports/'.$user->id.'/demo.pdf';
        Storage::disk('local')->put($path, 'dummy');

        $import = ResumeImport::query()->create([
            'user_id' => $user->id,
            'original_filename' => 'demo.pdf',
            'disk' => 'local',
            'storage_path' => $path,
            'status' => ResumeImport::STATUS_UPLOADED,
        ]);

        $this->actingAs($user)
            ->post(route('resume-imports.cancel', $import))
            ->assertRedirect(route('resume-imports.create'));

        $this->assertSame(ResumeImport::STATUS_CANCELLED, $import->fresh()->status);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_cross_user_cannot_access_import(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $import = ResumeImport::query()->create([
            'user_id' => $owner->id,
            'original_filename' => 'secret.pdf',
            'status' => ResumeImport::STATUS_UPLOADED,
        ]);

        $this->actingAs($intruder)
            ->get(route('resume-imports.show', $import))
            ->assertNotFound();
    }

    public function test_parse_does_not_call_ai_when_mocked_and_commit_waits_for_review(): void
    {
        $user = User::factory()->create();
        $import = ResumeImport::query()->create([
            'user_id' => $user->id,
            'original_filename' => 'cv.pdf',
            'status' => ResumeImport::STATUS_EXTRACTED,
            'extracted_text' => str_repeat('Experienced Laravel developer building ATS tools. ', 5),
        ]);

        $parser = Mockery::mock(ResumeImportParser::class);
        $parser->shouldReceive('parse')->once()->andReturn([
            'personal' => ['full_name' => 'Test User', 'email' => $user->email, 'additional_links' => []],
            'skills' => ['Laravel'],
            'work_experiences' => [],
            'projects' => [],
            'education' => [],
            'certifications' => [],
            'uncertain_fields' => [],
        ]);
        $this->app->instance(ResumeImportParser::class, $parser);

        app(ResumeImportService::class)->runParsing($user, $import);

        $this->assertSame(ResumeImport::STATUS_REVIEW, $import->fresh()->status);
        $this->assertSame(1, Profile::query()->where('user_id', $user->id)->count());
    }
}
