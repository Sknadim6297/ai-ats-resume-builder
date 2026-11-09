<?php

namespace App\Services\Profile;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Arr;

class MasterProfileService
{
    use EnsuresUserProfile;

    public function getOrInitialize(User $user): Profile
    {
        return $this->profileFor($user);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(User $user, array $data): Profile
    {
        $profile = $this->profileFor($user);

        $links = collect(Arr::get($data, 'additional_links', []))
            ->filter(fn ($link) => filled(Arr::get($link, 'label')) || filled(Arr::get($link, 'url')))
            ->map(fn ($link) => [
                'label' => trim((string) Arr::get($link, 'label', '')),
                'url' => trim((string) Arr::get($link, 'url', '')),
            ])
            ->values()
            ->all();

        $profile->fill([
            'full_name' => $data['full_name'],
            'headline' => $data['headline'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'location' => $data['location'] ?? null,
            'linkedin_url' => $data['linkedin_url'] ?? null,
            'github_url' => $data['github_url'] ?? null,
            'portfolio_url' => $data['portfolio_url'] ?? null,
            'professional_summary' => $data['professional_summary'] ?? null,
            'years_of_experience' => $data['years_of_experience'] ?? null,
            'additional_links' => $links,
        ]);

        $profile->save();

        return $profile->fresh();
    }
}
