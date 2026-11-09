<?php

namespace App\Services\Profile;

use App\Models\Skill;
use App\Models\User;
use App\Models\WorkExperience;
use App\Services\Profile\CurrentProfileService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class WorkExperienceService
{
    public function listForUser(User $user): Collection
    {
        $profile = app(CurrentProfileService::class)->resolve($user);

        return WorkExperience::query()
            ->where('user_id', $user->id)
            ->where('profile_id', $profile->id)
            ->with('technologies')
            ->orderByDesc('is_current')
            ->orderByDesc('start_date')
            ->get();
    }

    public function findOwned(User $user, int $id): WorkExperience
    {
        return WorkExperience::query()
            ->where('user_id', $user->id)
            ->with('technologies')
            ->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): WorkExperience
    {
        return DB::transaction(function () use ($user, $data) {
            $profile = app(CurrentProfileService::class)->resolve($user);
            $experience = WorkExperience::query()->create([
                'user_id' => $user->id,
                'profile_id' => $profile->id,
                ...$this->attributes($data),
            ]);

            $this->syncTechnologies($experience, $data['technologies'] ?? []);

            return $experience->load('technologies');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, WorkExperience $experience, array $data): WorkExperience
    {
        $this->assertOwned($user, $experience);

        return DB::transaction(function () use ($experience, $data) {
            $experience->update($this->attributes($data));
            $experience->technologies()->delete();
            $this->syncTechnologies($experience, $data['technologies'] ?? []);

            return $experience->fresh()->load('technologies');
        });
    }

    public function delete(User $user, WorkExperience $experience): void
    {
        $this->assertOwned($user, $experience);
        $experience->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $isCurrent = (bool) ($data['is_current'] ?? false);

        return [
            'company_name' => trim($data['company_name']),
            'job_title' => trim($data['job_title']),
            'employment_type' => $data['employment_type'],
            'location' => $data['location'] ?? null,
            'start_date' => $data['start_date'],
            'end_date' => $isCurrent ? null : ($data['end_date'] ?? null),
            'is_current' => $isCurrent,
            'responsibilities' => array_values(array_filter(array_map('trim', $data['responsibilities'] ?? []))),
            'achievements' => array_values(array_filter(array_map('trim', $data['achievements'] ?? []))),
        ];
    }

    /**
     * @param  list<string>  $technologies
     */
    private function syncTechnologies(WorkExperience $experience, array $technologies): void
    {
        foreach (array_values(array_unique(array_filter(array_map('trim', $technologies)))) as $index => $name) {
            $skill = Skill::query()->where('slug', Skill::makeSlug($name))->first();
            $experience->technologies()->create([
                'name' => $name,
                'skill_id' => $skill?->id,
                'sort_order' => $index,
            ]);
        }
    }

    private function assertOwned(User $user, WorkExperience $experience): void
    {
        if ($experience->user_id !== $user->id) {
            abort(403);
        }
    }
}
