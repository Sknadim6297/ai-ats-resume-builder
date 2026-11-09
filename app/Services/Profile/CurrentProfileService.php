<?php

namespace App\Services\Profile;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * UI selection of the working profile (session).
 * Background/JD/resume operations must use an explicit profile_id, not only this.
 */
class CurrentProfileService
{
    public const SESSION_KEY = 'current_profile_id';

    public function ensureDefault(User $user): Profile
    {
        $existing = Profile::query()
            ->where('user_id', $user->id)
            ->whereNull('archived_at')
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();

        if ($existing) {
            if (! $existing->is_default) {
                $this->forceSingleDefault($user, $existing);
            }

            return $existing->fresh();
        }

        return Profile::query()->create([
            'user_id' => $user->id,
            'name' => 'Default Profile',
            'full_name' => $user->name,
            'email' => $user->email,
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    public function resolve(User $user, ?int $preferredId = null): Profile
    {
        $id = $preferredId ?: (int) session(self::SESSION_KEY);

        if ($id > 0) {
            $profile = Profile::query()
                ->where('user_id', $user->id)
                ->whereKey($id)
                ->whereNull('archived_at')
                ->first();

            if ($profile) {
                return $profile;
            }
        }

        $profile = $this->ensureDefault($user);
        session([self::SESSION_KEY => $profile->id]);

        return $profile;
    }

    public function switchTo(User $user, int $profileId): Profile
    {
        $profile = Profile::query()
            ->where('user_id', $user->id)
            ->whereKey($profileId)
            ->whereNull('archived_at')
            ->firstOrFail();

        session([self::SESSION_KEY => $profile->id]);

        return $profile;
    }

    public function findOwned(User $user, int $profileId, bool $allowArchived = false): Profile
    {
        $query = Profile::query()->where('user_id', $user->id)->whereKey($profileId);
        if (! $allowArchived) {
            $query->whereNull('archived_at');
        }

        return $query->firstOrFail();
    }

    public function forceSingleDefault(User $user, Profile $profile): void
    {
        if ($profile->user_id !== $user->id) {
            abort(403);
        }

        DB::transaction(function () use ($user, $profile) {
            Profile::query()
                ->where('user_id', $user->id)
                ->where('id', '!=', $profile->id)
                ->update(['is_default' => false]);

            $profile->update([
                'is_default' => true,
                'archived_at' => null,
                'is_active' => true,
            ]);
        });
    }

    public function assertOwned(User $user, Profile $profile): void
    {
        if ($profile->user_id !== $user->id) {
            abort(403);
        }
    }

    public function assertCanMutate(User $user, Profile $profile): void
    {
        $this->assertOwned($user, $profile);
        if ($profile->archived_at !== null) {
            throw ValidationException::withMessages([
                'profile' => 'Archived profiles cannot be edited. Restore the profile first.',
            ]);
        }
    }
}
