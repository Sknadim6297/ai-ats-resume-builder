<?php

namespace App\Services\Profile;

use App\Models\Profile;
use App\Models\User;

trait EnsuresUserProfile
{
    protected function profileFor(User $user, ?Profile $profile = null): Profile
    {
        if ($profile) {
            if ($profile->user_id !== $user->id) {
                abort(403);
            }

            return $profile;
        }

        return app(CurrentProfileService::class)->resolve($user);
    }
}
