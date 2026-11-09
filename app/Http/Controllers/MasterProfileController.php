<?php

namespace App\Http\Controllers;

use App\Http\Requests\MasterProfile\SaveMasterProfileRequest;
use App\Services\Profile\MasterProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MasterProfileController extends Controller
{
    public function __construct(
        private readonly MasterProfileService $profiles,
    ) {}

    public function edit(Request $request): Response
    {
        $profile = $this->profiles->getOrInitialize($request->user());

        return Inertia::render('MasterProfile/Edit', [
            'profile' => [
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
            ],
            'status' => session('status'),
        ]);
    }

    public function update(SaveMasterProfileRequest $request): RedirectResponse
    {
        $this->profiles->save($request->user(), $request->validated());

        return redirect()
            ->route('master-profile.edit')
            ->with('status', 'master-profile-saved');
    }
}
