<?php

namespace App\Services\Profile;

use App\Models\Education;
use App\Models\User;
use App\Services\Profile\CurrentProfileService;
use Illuminate\Database\Eloquent\Collection;

class EducationService
{
    public function listForUser(User $user): Collection
    {
        $profile = app(CurrentProfileService::class)->resolve($user);

        return Education::query()
            ->where('user_id', $user->id)
            ->where('profile_id', $profile->id)
            ->orderByDesc('start_year')
            ->orderByDesc('end_year')
            ->get();
    }

    public function findOwned(User $user, int $id): Education
    {
        return Education::query()
            ->where('user_id', $user->id)
            ->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): Education
    {
        $profile = app(CurrentProfileService::class)->resolve($user);

        return Education::query()->create([
            'user_id' => $user->id,
            'profile_id' => $profile->id,
            ...$this->attributes($data),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, Education $education, array $data): Education
    {
        $this->assertOwned($user, $education);
        $education->update($this->attributes($data));

        return $education->fresh();
    }

    public function delete(User $user, Education $education): void
    {
        $this->assertOwned($user, $education);
        $education->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'institution_name' => trim($data['institution_name']),
            'degree' => trim($data['degree']),
            'field_of_study' => $data['field_of_study'] ?? null,
            'start_year' => (int) $data['start_year'],
            'end_year' => isset($data['end_year']) && $data['end_year'] !== '' ? (int) $data['end_year'] : null,
            'grade' => $data['grade'] ?? null,
            'description' => $data['description'] ?? null,
        ];
    }

    private function assertOwned(User $user, Education $education): void
    {
        if ($education->user_id !== $user->id) {
            abort(403);
        }
    }
}
