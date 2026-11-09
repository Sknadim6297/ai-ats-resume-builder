<?php

namespace App\Services\Profile;

use App\Models\Education;
use App\Models\Profile;
use App\Models\Project;
use App\Models\ProjectFeature;
use App\Models\ProjectResponsibility;
use App\Models\ProjectTechnology;
use App\Models\User;
use App\Models\WorkExperience;
use App\Models\WorkExperienceTechnology;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProfessionalProfileService
{
    public function __construct(
        private readonly CurrentProfileService $current,
    ) {}

    /**
     * @param  array{search?:string,status?:string}  $filters
     */
    public function listForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = Profile::query()
            ->where('user_id', $user->id)
            ->withCount([
                'skills as skills_count',
                'projects as projects_count',
            ])
            ->orderByDesc('is_default')
            ->orderBy('name');

        if (($filters['status'] ?? null) === 'archived') {
            $query->whereNotNull('archived_at');
        } elseif (($filters['status'] ?? null) === 'active') {
            $query->whereNull('archived_at')->where('is_active', true);
        } else {
            $query->whereNull('archived_at');
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('headline', 'like', '%'.$search.'%')
                    ->orWhere('full_name', 'like', '%'.$search.'%');
            });
        }

        return $query->paginate(12)->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): Profile
    {
        $count = Profile::query()->where('user_id', $user->id)->whereNull('archived_at')->count();
        if ($count >= 50) {
            throw ValidationException::withMessages([
                'name' => 'Profile limit reached (50 active/archived-visible profiles). Archive unused profiles first.',
            ]);
        }

        return DB::transaction(function () use ($user, $data, $count) {
            $makeDefault = (bool) ($data['is_default'] ?? $count === 0);

            $profile = Profile::query()->create([
                'user_id' => $user->id,
                'name' => trim((string) $data['name']),
                'full_name' => $data['full_name'] ?? $user->name,
                'headline' => $data['headline'] ?? null,
                'email' => $data['email'] ?? $user->email,
                'phone' => $data['phone'] ?? null,
                'location' => $data['location'] ?? null,
                'linkedin_url' => $data['linkedin_url'] ?? null,
                'github_url' => $data['github_url'] ?? null,
                'portfolio_url' => $data['portfolio_url'] ?? null,
                'professional_summary' => $data['professional_summary'] ?? null,
                'years_of_experience' => $data['years_of_experience'] ?? null,
                'additional_links' => $data['additional_links'] ?? [],
                'is_default' => false,
                'is_active' => true,
            ]);

            if ($makeDefault) {
                $this->current->forceSingleDefault($user, $profile);
            }

            return $profile->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, Profile $profile, array $data): Profile
    {
        $this->current->assertCanMutate($user, $profile);

        $profile->fill([
            'name' => $data['name'] ?? $profile->name,
            'full_name' => $data['full_name'] ?? $profile->full_name,
            'headline' => $data['headline'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'location' => $data['location'] ?? null,
            'linkedin_url' => $data['linkedin_url'] ?? null,
            'github_url' => $data['github_url'] ?? null,
            'portfolio_url' => $data['portfolio_url'] ?? null,
            'professional_summary' => $data['professional_summary'] ?? null,
            'years_of_experience' => $data['years_of_experience'] ?? null,
            'additional_links' => $data['additional_links'] ?? $profile->additional_links,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $profile->is_active,
        ]);
        $profile->save();

        return $profile->fresh();
    }

    public function rename(User $user, Profile $profile, string $name): Profile
    {
        $this->current->assertCanMutate($user, $profile);
        $profile->update(['name' => trim($name)]);

        return $profile->fresh();
    }

    public function setDefault(User $user, Profile $profile): Profile
    {
        $this->current->assertOwned($user, $profile);
        if ($profile->archived_at !== null) {
            throw ValidationException::withMessages([
                'profile' => 'Restore the profile before setting it as default.',
            ]);
        }
        $this->current->forceSingleDefault($user, $profile);
        session([CurrentProfileService::SESSION_KEY => $profile->id]);

        return $profile->fresh();
    }

    public function archive(User $user, Profile $profile): void
    {
        $this->current->assertOwned($user, $profile);

        if ($profile->is_default) {
            throw ValidationException::withMessages([
                'profile' => 'Set another profile as default before archiving this one.',
            ]);
        }

        $activeCount = Profile::query()
            ->where('user_id', $user->id)
            ->whereNull('archived_at')
            ->count();

        if ($activeCount <= 1) {
            throw ValidationException::withMessages([
                'profile' => 'Keep at least one active profile.',
            ]);
        }

        $profile->update([
            'archived_at' => now(),
            'is_active' => false,
            'is_default' => false,
        ]);

        if ((int) session(CurrentProfileService::SESSION_KEY) === $profile->id) {
            $fallback = $this->current->ensureDefault($user);
            session([CurrentProfileService::SESSION_KEY => $fallback->id]);
        }
    }

    public function restore(User $user, Profile $profile): Profile
    {
        $this->current->assertOwned($user, $profile);
        $profile->update([
            'archived_at' => null,
            'is_active' => true,
        ]);

        return $profile->fresh();
    }

    public function duplicate(User $user, Profile $source, ?string $newName = null): Profile
    {
        $this->current->assertOwned($user, $source);

        return DB::transaction(function () use ($user, $source, $newName) {
            $copy = Profile::query()->create([
                'user_id' => $user->id,
                'name' => $newName ?: mb_substr($source->name.' (Copy)', 0, 120),
                'full_name' => $source->full_name,
                'headline' => $source->headline,
                'email' => $source->email,
                'phone' => $source->phone,
                'location' => $source->location,
                'linkedin_url' => $source->linkedin_url,
                'github_url' => $source->github_url,
                'portfolio_url' => $source->portfolio_url,
                'professional_summary' => $source->professional_summary,
                'years_of_experience' => $source->years_of_experience,
                'additional_links' => $source->additional_links,
                'is_verified' => false,
                'is_default' => false,
                'is_active' => true,
            ]);

            foreach ($source->skills()->get() as $skill) {
                $copy->skills()->attach($skill->id, [
                    'proficiency' => $skill->pivot->proficiency,
                    'years_experience' => $skill->pivot->years_experience,
                    'last_used_year' => $skill->pivot->last_used_year,
                    'is_primary' => (bool) $skill->pivot->is_primary,
                    'is_verified' => (bool) $skill->pivot->is_verified,
                    'is_active' => (bool) $skill->pivot->is_active,
                    'source' => $skill->pivot->source ?? 'manual',
                ]);
            }

            foreach (Project::query()->where('profile_id', $source->id)->with(['technologies', 'features', 'responsibilities'])->get() as $project) {
                $newProject = Project::query()->create([
                    'user_id' => $user->id,
                    'profile_id' => $copy->id,
                    'name' => $project->name,
                    'short_description' => $project->short_description,
                    'detailed_description' => $project->detailed_description,
                    'project_type' => $project->project_type,
                    'github_url' => $project->github_url,
                    'live_url' => $project->live_url,
                    'start_date' => $project->start_date,
                    'end_date' => $project->end_date,
                    'is_ongoing' => $project->is_ongoing,
                    'is_active' => $project->is_active,
                    'include_in_resume' => $project->include_in_resume,
                ]);
                foreach ($project->technologies as $tech) {
                    ProjectTechnology::query()->create([
                        'project_id' => $newProject->id,
                        'name' => $tech->name,
                        'skill_id' => $tech->skill_id,
                        'sort_order' => $tech->sort_order,
                    ]);
                }
                foreach ($project->features as $feature) {
                    ProjectFeature::query()->create([
                        'project_id' => $newProject->id,
                        'description' => $feature->description,
                        'sort_order' => $feature->sort_order,
                    ]);
                }
                foreach ($project->responsibilities as $item) {
                    ProjectResponsibility::query()->create([
                        'project_id' => $newProject->id,
                        'description' => $item->description,
                        'sort_order' => $item->sort_order,
                    ]);
                }
            }

            foreach (WorkExperience::query()->where('profile_id', $source->id)->with('technologies')->get() as $exp) {
                $newExp = WorkExperience::query()->create([
                    'user_id' => $user->id,
                    'profile_id' => $copy->id,
                    'company_name' => $exp->company_name,
                    'job_title' => $exp->job_title,
                    'employment_type' => $exp->employment_type,
                    'location' => $exp->location,
                    'start_date' => $exp->start_date,
                    'end_date' => $exp->end_date,
                    'is_current' => $exp->is_current,
                    'responsibilities' => $exp->responsibilities,
                    'achievements' => $exp->achievements,
                ]);
                foreach ($exp->technologies as $tech) {
                    WorkExperienceTechnology::query()->create([
                        'work_experience_id' => $newExp->id,
                        'name' => $tech->name,
                        'skill_id' => $tech->skill_id,
                    ]);
                }
            }

            foreach (Education::query()->where('profile_id', $source->id)->get() as $edu) {
                Education::query()->create([
                    'user_id' => $user->id,
                    'profile_id' => $copy->id,
                    'institution_name' => $edu->institution_name,
                    'degree' => $edu->degree,
                    'field_of_study' => $edu->field_of_study,
                    'start_year' => $edu->start_year,
                    'end_year' => $edu->end_year,
                    'grade' => $edu->grade,
                ]);
            }

            return $copy->fresh();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function present(Profile $profile): array
    {
        return [
            'id' => $profile->id,
            'name' => $profile->name,
            'full_name' => $profile->full_name,
            'headline' => $profile->headline,
            'email' => $profile->email,
            'phone' => $profile->phone,
            'location' => $profile->location,
            'linkedin_url' => $profile->linkedin_url,
            'github_url' => $profile->github_url,
            'portfolio_url' => $profile->portfolio_url,
            'professional_summary' => $profile->professional_summary,
            'years_of_experience' => $profile->years_of_experience,
            'additional_links' => $profile->additional_links ?? [],
            'is_default' => (bool) $profile->is_default,
            'is_active' => (bool) $profile->is_active,
            'archived_at' => optional($profile->archived_at)?->toDateTimeString(),
            'skills_count' => $profile->skills_count ?? $profile->skills()->count(),
            'projects_count' => $profile->projects_count ?? $profile->projects()->count(),
            'updated_at' => optional($profile->updated_at)?->toDateTimeString(),
        ];
    }
}
