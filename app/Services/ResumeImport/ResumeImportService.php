<?php

namespace App\Services\ResumeImport;

use App\Models\Education;
use App\Models\Profile;
use App\Models\Project;
use App\Models\ProjectTechnology;
use App\Models\ResumeImport;
use App\Models\Skill;
use App\Models\User;
use App\Models\WorkExperience;
use App\Models\WorkExperienceTechnology;
use App\Services\AI\AiConfiguration;
use App\Services\Profile\CurrentProfileService;
use App\Services\Profile\ProfessionalProfileService;
use App\Services\Resume\ResumeSkillCategorizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ResumeImportService
{
    private const DISK = 'local';

    private const STORAGE_PREFIX = 'resume-imports';

    public function __construct(
        private readonly ResumeTextExtractor $extractor,
        private readonly ResumeImportParser $parser,
        private readonly ProfessionalProfileService $profiles,
        private readonly CurrentProfileService $current,
        private readonly ResumeImportSchema $schema,
        private readonly AiConfiguration $aiConfiguration,
        private readonly ResumeSkillCategorizer $skillCategorizer,
    ) {}

    public function findOwned(User $user, int $id): ResumeImport
    {
        return ResumeImport::query()
            ->where('user_id', $user->id)
            ->findOrFail($id);
    }

    public function storeUpload(User $user, UploadedFile $file): ResumeImport
    {
        $this->ensureUploadNotRateLimited($user);

        $mime = (string) $file->getMimeType();
        $dir = self::STORAGE_PREFIX.'/'.$user->id;
        $name = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs($dir, $name, self::DISK);

        RateLimiter::hit($this->uploadRateKey($user), 60);

        return ResumeImport::query()->create([
            'user_id' => $user->id,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $mime,
            'file_size' => (int) $file->getSize(),
            'disk' => self::DISK,
            'storage_path' => $path,
            'status' => ResumeImport::STATUS_UPLOADED,
        ]);
    }

    public function runExtraction(User $user, ResumeImport $import): ResumeImport
    {
        $this->assertOwned($user, $import);

        if ($import->isTerminal()) {
            throw ValidationException::withMessages([
                'resume' => 'This import is already finished.',
            ]);
        }

        $import->update(['status' => ResumeImport::STATUS_EXTRACTING, 'safe_error' => null]);

        try {
            $absolute = Storage::disk(self::DISK)->path((string) $import->storage_path);
            $extracted = $this->extractor->extract(
                $absolute,
                $import->mime_type,
                $import->original_filename,
            );

            $import->update([
                'status' => ResumeImport::STATUS_EXTRACTED,
                'extraction_method' => $extracted['method'],
                'extracted_text' => $extracted['text'],
            ]);
        } catch (ValidationException $e) {
            $import->update([
                'status' => ResumeImport::STATUS_FAILED,
                'safe_error' => collect($e->errors())->flatten()->first() ?: 'Extraction failed.',
            ]);

            throw $e;
        } catch (\Throwable) {
            $import->update([
                'status' => ResumeImport::STATUS_FAILED,
                'safe_error' => 'Extraction failed unexpectedly.',
            ]);

            throw ValidationException::withMessages([
                'resume' => 'Extraction failed unexpectedly.',
            ]);
        }

        return $import->fresh();
    }

    public function runParsing(User $user, ResumeImport $import): ResumeImport
    {
        $this->assertOwned($user, $import);

        if (! in_array($import->status, [
            ResumeImport::STATUS_EXTRACTED,
            ResumeImport::STATUS_PARSED,
            ResumeImport::STATUS_REVIEW,
            ResumeImport::STATUS_FAILED,
        ], true)) {
            throw ValidationException::withMessages([
                'resume' => 'Extract the resume text before parsing.',
            ]);
        }

        if (blank($import->extracted_text)) {
            throw ValidationException::withMessages([
                'resume' => 'No extracted text available.',
            ]);
        }

        // Resume AI parsing often exceeds PHP's default 60s request limit.
        $providerName = $this->aiConfiguration->providerForTask('resume_import');
        $aiTimeout = max(30, (int) config('ai.providers.'.$providerName.'.timeout', 90));
        if (function_exists('set_time_limit')) {
            @set_time_limit($aiTimeout + 30);
        }

        $import->update(['status' => ResumeImport::STATUS_PARSING, 'safe_error' => null]);

        try {
            $parsed = $this->parser->parse((string) $import->extracted_text);
            $validated = $parsed['payload'];

            $import->update([
                'status' => ResumeImport::STATUS_REVIEW,
                'parsed_payload' => $validated,
                'review_payload' => $validated,
                'provider' => $parsed['provider'] ?? $providerName,
                'model' => $parsed['model'] ?? config('ai.providers.'.$providerName.'.model'),
            ]);
        } catch (ValidationException $e) {
            $safe = collect($e->errors())->flatten()->first() ?: 'Parsing failed.';

            $import->update([
                'status' => ResumeImport::STATUS_FAILED,
                'safe_error' => $safe,
            ]);

            throw $e;
        } catch (\Throwable $e) {
            // Avoid logging request payloads that might contain secrets; report the throwable only.
            report($e);

            $import->update([
                'status' => ResumeImport::STATUS_FAILED,
                'safe_error' => 'AI resume parsing failed. Please try again.',
            ]);

            throw ValidationException::withMessages([
                'resume' => 'AI resume parsing failed. Please try again.',
            ]);
        }

        return $import->fresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function saveReview(User $user, ResumeImport $import, array $payload): ResumeImport
    {
        $this->assertOwned($user, $import);

        $validated = $this->schema->validate($payload['review_payload'] ?? $payload);

        $import->update([
            'review_payload' => $validated,
            'status' => ResumeImport::STATUS_REVIEW,
        ]);

        return $import->fresh();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function commit(User $user, ResumeImport $import, array $input): Profile
    {
        $this->assertOwned($user, $import);

        if ($import->status !== ResumeImport::STATUS_REVIEW) {
            throw ValidationException::withMessages([
                'resume' => 'Complete the review step before saving.',
            ]);
        }

        $review = $import->review_payload;
        if (is_array($input['review_payload'] ?? null)) {
            $review = $this->schema->validate($input['review_payload']);
            $import->update(['review_payload' => $review]);
        }

        if (! is_array($review)) {
            throw ValidationException::withMessages([
                'resume' => 'Review data is missing.',
            ]);
        }

        $strategy = (string) ($input['merge_strategy'] ?? ResumeImport::MERGE_NEW_PROFILE);

        return DB::transaction(function () use ($user, $import, $review, $strategy, $input) {
            if ($strategy === ResumeImport::MERGE_MERGE) {
                $profileId = (int) ($input['profile_id'] ?? 0);
                if ($profileId <= 0) {
                    throw ValidationException::withMessages([
                        'profile_id' => 'Select a profile to merge into.',
                    ]);
                }
                $profile = $this->current->findOwned($user, $profileId);
                $this->applyReviewToProfile($user, $profile, $review, merge: true);
            } else {
                $name = trim((string) ($input['new_profile_name'] ?? ''));
                if ($name === '') {
                    $name = $this->defaultImportProfileName($review);
                }
                $profile = $this->profiles->create($user, array_merge(
                    $this->personalToProfileData($review['personal'] ?? []),
                    ['name' => $name]
                ));
                $this->applyReviewToProfile($user, $profile, $review, merge: false);
            }

            $import->update([
                'status' => ResumeImport::STATUS_COMPLETED,
                'profile_id' => $profile->id,
                'merge_strategy' => $strategy,
                'confirmed_at' => now(),
            ]);

            $this->deleteStoredFile($import);
            $import->update(['storage_path' => null]);

            session([CurrentProfileService::SESSION_KEY => $profile->id]);

            return $profile->fresh();
        });
    }

    public function cancel(User $user, ResumeImport $import): void
    {
        $this->assertOwned($user, $import);

        if ($import->status === ResumeImport::STATUS_COMPLETED) {
            throw ValidationException::withMessages([
                'resume' => 'Completed imports cannot be cancelled.',
            ]);
        }

        $this->deleteStoredFile($import);

        $import->update([
            'status' => ResumeImport::STATUS_CANCELLED,
            'storage_path' => null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function present(ResumeImport $import): array
    {
        return [
            'id' => $import->id,
            'original_filename' => $import->original_filename,
            'mime_type' => $import->mime_type,
            'file_size' => $import->file_size,
            'status' => $import->status,
            'extraction_method' => $import->extraction_method,
            'safe_error' => $import->safe_error,
            'parsed_payload' => $import->parsed_payload,
            'review_payload' => $import->review_payload,
            'merge_strategy' => $import->merge_strategy,
            'profile_id' => $import->profile_id,
            'created_at' => optional($import->created_at)?->toDateTimeString(),
            'confirmed_at' => optional($import->confirmed_at)?->toDateTimeString(),
        ];
    }

    private function applyReviewToProfile(User $user, Profile $profile, array $review, bool $merge): void
    {
        if (! $merge) {
            // Fresh profile already has personal fields from create().
        } else {
            $this->profiles->update($user, $profile, $this->personalToProfileData($review['personal'] ?? []));
        }

        $this->attachSkillsToProfile($profile, $review['skills'] ?? [], $merge);

        foreach ($review['work_experiences'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            if ($merge && $this->experienceExists($profile, $row)) {
                continue;
            }
            $startDate = $this->nullableDate($row['start_date'] ?? null);
            if ($startDate === null) {
                // start_date is required in schema; skip incomplete AI rows rather than inventing dates.
                continue;
            }
            $exp = WorkExperience::query()->create([
                'user_id' => $user->id,
                'profile_id' => $profile->id,
                'company_name' => $row['company_name'] ?? '',
                'job_title' => $row['job_title'] ?? '',
                'employment_type' => $row['employment_type'] ?? 'Full-time',
                'location' => $row['location'] ?? null,
                'start_date' => $startDate,
                'end_date' => $this->nullableDate($row['end_date'] ?? null),
                'is_current' => (bool) ($row['is_current'] ?? false),
                'responsibilities' => $row['responsibilities'] ?? [],
                'achievements' => $row['achievements'] ?? [],
            ]);
            foreach ($row['technologies'] ?? [] as $tech) {
                if (! is_string($tech) || $tech === '') {
                    continue;
                }
                WorkExperienceTechnology::query()->create([
                    'work_experience_id' => $exp->id,
                    'name' => $tech,
                ]);
            }
        }

        foreach ($review['projects'] ?? [] as $row) {
            if (! is_array($row) || empty($row['name'])) {
                continue;
            }
            if ($merge && Project::query()
                ->where('profile_id', $profile->id)
                ->where('name', $row['name'])
                ->exists()) {
                continue;
            }
            $project = Project::query()->create([
                'user_id' => $user->id,
                'profile_id' => $profile->id,
                'name' => $row['name'],
                'short_description' => $row['short_description'] ?? null,
                'detailed_description' => $row['detailed_description'] ?? null,
                'project_type' => $row['project_type'] ?? 'Other',
                'github_url' => $row['github_url'] ?? null,
                'live_url' => $row['live_url'] ?? null,
                'is_active' => true,
                'include_in_resume' => true,
            ]);
            foreach ($row['technologies'] ?? [] as $index => $tech) {
                if (! is_string($tech) || $tech === '') {
                    continue;
                }
                ProjectTechnology::query()->create([
                    'project_id' => $project->id,
                    'name' => $tech,
                    'sort_order' => $index,
                ]);
            }
        }

        foreach ($review['education'] ?? [] as $row) {
            if (! is_array($row) || empty($row['institution_name'])) {
                continue;
            }
            if ($merge && Education::query()
                ->where('profile_id', $profile->id)
                ->where('institution_name', $row['institution_name'])
                ->where('degree', $row['degree'] ?? null)
                ->exists()) {
                continue;
            }
            Education::query()->create([
                'user_id' => $user->id,
                'profile_id' => $profile->id,
                'institution_name' => $row['institution_name'],
                'degree' => $row['degree'] ?? null,
                'field_of_study' => $row['field_of_study'] ?? null,
                'start_year' => $row['start_year'] ?? null,
                'end_year' => $row['end_year'] ?? null,
                'grade' => $row['grade'] ?? null,
            ]);
        }
    }

    /**
     * @param  list<string>  $skillNames
     */
    private function attachSkillsToProfile(Profile $profile, array $skillNames, bool $skipExisting): void
    {
        $grouped = $this->skillCategorizer->group(array_values(array_filter(
            array_map(fn ($name) => is_string($name) ? Skill::normalizeName($name) : '', $skillNames)
        )));
        $categoryByName = [];
        foreach ($grouped as $group) {
            foreach ($group['skills'] as $name) {
                $categoryByName[mb_strtolower($name)] = $group['category'];
            }
        }

        foreach ($skillNames as $skillName) {
            if (! is_string($skillName) || trim($skillName) === '') {
                continue;
            }
            $normalized = Skill::normalizeName($skillName);
            $slug = Skill::makeSlug($normalized);
            $category = $categoryByName[mb_strtolower($normalized)] ?? null;

            $skill = Skill::query()->firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => $normalized,
                    'category' => $category,
                ]
            );

            if ($skill->category === null && $category !== null) {
                $skill->update(['category' => $category]);
            }

            if ($skipExisting && $profile->skills()->where('skills.id', $skill->id)->exists()) {
                continue;
            }
            if ($profile->skills()->where('skills.id', $skill->id)->exists()) {
                continue;
            }
            $profile->skills()->attach($skill->id, [
                'proficiency' => 3,
                'is_verified' => false,
                'is_active' => true,
                'source' => 'imported',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $personal
     * @return array<string, mixed>
     */
    private function personalToProfileData(array $personal): array
    {
        return [
            'full_name' => $personal['full_name'] ?? null,
            'headline' => $personal['headline'] ?? null,
            'email' => $personal['email'] ?? null,
            'phone' => $personal['phone'] ?? null,
            'location' => $personal['location'] ?? null,
            'linkedin_url' => $personal['linkedin_url'] ?? null,
            'github_url' => $personal['github_url'] ?? null,
            'portfolio_url' => $personal['portfolio_url'] ?? null,
            'professional_summary' => $personal['professional_summary'] ?? null,
            'years_of_experience' => $personal['years_of_experience'] ?? null,
            'additional_links' => $personal['additional_links'] ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function experienceExists(Profile $profile, array $row): bool
    {
        return WorkExperience::query()
            ->where('profile_id', $profile->id)
            ->where('company_name', $row['company_name'] ?? '')
            ->where('job_title', $row['job_title'] ?? '')
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $review
     */
    private function defaultImportProfileName(array $review): string
    {
        $headline = $review['personal']['headline'] ?? null;
        if (is_string($headline) && trim($headline) !== '') {
            return mb_substr(trim($headline), 0, 120);
        }

        return 'Imported Profile';
    }

    private function nullableDate(?string $value): ?string
    {
        if (! $value) {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }
        if (preg_match('/^\d{4}$/', $value)) {
            return $value.'-01-01';
        }

        return null;
    }

    private function deleteStoredFile(ResumeImport $import): void
    {
        if ($import->storage_path) {
            Storage::disk((string) ($import->disk ?: self::DISK))->delete($import->storage_path);
        }
    }

    private function assertOwned(User $user, ResumeImport $import): void
    {
        if (! $import->isOwnedBy($user)) {
            abort(403);
        }
    }

    private function ensureUploadNotRateLimited(User $user): void
    {
        $max = (int) config('ai.resume_import.rate_limit_per_minute', 5);
        $key = $this->uploadRateKey($user);

        if (RateLimiter::tooManyAttempts($key, $max)) {
            throw ValidationException::withMessages([
                'resume' => 'Too many uploads. Please wait a minute and try again.',
            ]);
        }
    }

    private function uploadRateKey(User $user): string
    {
        return 'resume-import-upload:'.$user->id;
    }
}
