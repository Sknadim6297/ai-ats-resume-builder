<?php

namespace App\Services\Profile;

use App\Models\Profile;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SkillManagerService
{
    use EnsuresUserProfile;

    /**
     * @param  array{search?:string,category?:string,status?:string}  $filters
     */
    public function listForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        $profile = $this->profileFor($user);

        $query = $profile->skills()->orderBy('skills.name');

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($builder) use ($search) {
                $builder->where('skills.name', 'like', '%'.$search.'%')
                    ->orWhere('skills.category', 'like', '%'.$search.'%');
            });
        }

        if (! empty($filters['category'])) {
            $query->where('skills.category', $filters['category']);
        }

        if (($filters['status'] ?? null) === 'active') {
            $query->wherePivot('is_active', true);
        }

        if (($filters['status'] ?? null) === 'inactive') {
            $query->wherePivot('is_active', false);
        }

        return $query->paginate(12)->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function add(User $user, array $data): void
    {
        $profile = $this->profileFor($user);
        [$name, $slug] = $this->normalized($data['name']);

        DB::transaction(function () use ($profile, $name, $slug, $data) {
            $skill = Skill::query()->firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'category' => $data['category'] ?? null,
                ]
            );

            // Do not mutate a shared catalog row's category for other users.

            if ($profile->skills()->where('skills.id', $skill->id)->exists()) {
                throw ValidationException::withMessages([
                    'name' => 'This skill is already saved on your profile.',
                ]);
            }

            $profile->skills()->attach($skill->id, $this->pivotAttributes($data));
        });
    }

    /**
     * Idempotently attach a skill to the master profile.
     * Does not detach or delete skills when job-specific decisions change.
     *
     * @param  array<string, mixed>  $data
     */
    public function ensureAttached(User $user, string $skillName, array $data = [], ?\App\Models\Profile $profile = null): Skill
    {
        $profile = $this->profileFor($user, $profile);
        [$name, $slug] = $this->normalized($skillName);

        return DB::transaction(function () use ($profile, $name, $slug, $data) {
            $skill = Skill::query()->firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'category' => $data['category'] ?? null,
                ]
            );

            $existing = $profile->skills()->where('skills.id', $skill->id)->first();

            if ($existing) {
                $updates = ['is_active' => true];
                // Never silently upgrade imported/unverified skills to verified.
                if (array_key_exists('is_verified', $data)) {
                    $updates['is_verified'] = (bool) $data['is_verified'];
                }
                if (array_key_exists('source', $data) && ! $existing->pivot->source) {
                    $updates['source'] = $data['source'];
                }
                $profile->skills()->updateExistingPivot($skill->id, $updates);

                return $skill;
            }

            $profile->skills()->attach($skill->id, $this->pivotAttributes(array_merge($data, [
                'is_active' => true,
            ])));

            return $skill;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, int $skillId, array $data): void
    {
        $profile = $this->profileFor($user);
        $this->assertOwned($profile, $skillId);
        [$name, $slug] = $this->normalized($data['name']);

        DB::transaction(function () use ($profile, $skillId, $name, $slug, $data) {
            $current = Skill::query()->findOrFail($skillId);
            $shared = $this->skillIsSharedBeyondProfile($skillId, $profile->id);

            // Rename: move this profile to a distinct catalog row instead of
            // rewriting a shared skill used by other profiles.
            if ($current->slug !== $slug) {
                $target = Skill::query()->firstOrCreate(
                    ['slug' => $slug],
                    [
                        'name' => $name,
                        'category' => $data['category'] ?? $current->category,
                    ]
                );

                if ($target->id !== $skillId && $profile->skills()->where('skills.id', $target->id)->exists()) {
                    throw ValidationException::withMessages([
                        'name' => 'This skill is already saved on your profile.',
                    ]);
                }

                if ($target->id !== $skillId) {
                    $profile->skills()->detach($skillId);
                    $profile->skills()->attach($target->id, $this->pivotAttributes($data));
                    $this->deleteOrphanSkill($skillId);

                    return;
                }
            }

            if (! $shared) {
                $current->update([
                    'name' => $name,
                    'slug' => $slug,
                    'category' => $data['category'] ?? null,
                ]);
            }

            $profile->skills()->updateExistingPivot($skillId, $this->pivotAttributes($data));
        });
    }

    public function delete(User $user, int $skillId): void
    {
        $profile = $this->profileFor($user);
        $this->assertOwned($profile, $skillId);

        DB::transaction(function () use ($profile, $skillId) {
            $profile->skills()->detach($skillId);
            $this->deleteOrphanSkill($skillId);
        });
    }

    public function toggleActive(User $user, int $skillId): void
    {
        $profile = $this->profileFor($user);
        $skill = $this->assertOwned($profile, $skillId);

        $profile->skills()->updateExistingPivot($skillId, [
            'is_active' => ! (bool) $skill->pivot->is_active,
        ]);
    }

    /**
     * @return array{0:string,1:string}
     */
    private function normalized(string $raw): array
    {
        $name = Skill::normalizeName($raw);
        $slug = Skill::makeSlug($name);

        if ($slug === '') {
            throw ValidationException::withMessages([
                'name' => 'Skill name is invalid.',
            ]);
        }

        return [$name, $slug];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function pivotAttributes(array $data): array
    {
        return [
            'proficiency' => $data['proficiency'] ?? 3,
            'years_experience' => $data['years_experience'] ?? null,
            'last_used_year' => $data['last_used_year'] ?? null,
            'is_primary' => (bool) ($data['is_primary'] ?? false),
            'is_verified' => array_key_exists('is_verified', $data) ? (bool) $data['is_verified'] : true,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
            'source' => $data['source'] ?? 'manual',
        ];
    }

    private function assertOwned(Profile $profile, int $skillId): Skill
    {
        $skill = $profile->skills()->where('skills.id', $skillId)->first();

        if (! $skill) {
            abort(404);
        }

        return $skill;
    }

    private function skillIsSharedBeyondProfile(int $skillId, int $profileId): bool
    {
        return DB::table('profile_skills')
            ->where('skill_id', $skillId)
            ->where('profile_id', '!=', $profileId)
            ->exists();
    }

    private function deleteOrphanSkill(int $skillId): void
    {
        $stillUsed = DB::table('profile_skills')->where('skill_id', $skillId)->exists()
            || DB::table('project_technologies')->where('skill_id', $skillId)->exists()
            || DB::table('work_experience_technologies')->where('skill_id', $skillId)->exists();

        if (! $stillUsed) {
            Skill::query()->whereKey($skillId)->delete();
        }
    }
}
