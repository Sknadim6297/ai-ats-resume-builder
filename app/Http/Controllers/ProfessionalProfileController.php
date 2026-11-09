<?php

namespace App\Http\Controllers;

use App\Http\Requests\Profiles\DuplicateProfileRequest;
use App\Http\Requests\Profiles\StoreProfessionalProfileRequest;
use App\Http\Requests\Profiles\UpdateProfessionalProfileRequest;
use App\Models\Profile;
use App\Services\Profile\CurrentProfileService;
use App\Services\Profile\ProfessionalProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfessionalProfileController extends Controller
{
    public function __construct(
        private readonly ProfessionalProfileService $profiles,
        private readonly CurrentProfileService $current,
    ) {}

    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->string('search')->toString(),
            'status' => $request->string('status')->toString(),
        ];

        $paginator = $this->profiles->listForUser($request->user(), array_filter($filters));

        $current = $this->current->resolve($request->user());

        return Inertia::render('Profiles/Index', [
            'profiles' => $paginator->through(fn (Profile $profile) => $this->profiles->present($profile)),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'status' => $filters['status'] ?: 'active',
            ],
            'current_profile_id' => $current->id,
            'status' => session('status'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Profiles/Form', [
            'profile' => null,
            'status' => session('status'),
        ]);
    }

    public function store(StoreProfessionalProfileRequest $request): RedirectResponse
    {
        $profile = $this->profiles->create($request->user(), $request->validated());

        return redirect()
            ->route('profiles.index')
            ->with('status', 'profile-created');
    }

    public function edit(Request $request, Profile $profile): Response
    {
        $owned = $this->current->findOwned($request->user(), $profile->id, allowArchived: true);

        return Inertia::render('Profiles/Form', [
            'profile' => $this->profiles->present($owned),
            'status' => session('status'),
        ]);
    }

    public function update(UpdateProfessionalProfileRequest $request, Profile $profile): RedirectResponse
    {
        $owned = $this->current->findOwned($request->user(), $profile->id, allowArchived: true);
        $this->profiles->update($request->user(), $owned, $request->validated());

        return redirect()
            ->route('profiles.index')
            ->with('status', 'profile-updated');
    }

    public function duplicate(DuplicateProfileRequest $request, Profile $profile): RedirectResponse
    {
        $owned = $this->current->findOwned($request->user(), $profile->id);
        $copy = $this->profiles->duplicate($request->user(), $owned, $request->validated('name'));

        return redirect()
            ->route('profiles.edit', $copy)
            ->with('status', 'profile-duplicated');
    }

    public function setDefault(Request $request, Profile $profile): RedirectResponse
    {
        $owned = $this->current->findOwned($request->user(), $profile->id, allowArchived: true);
        $this->profiles->setDefault($request->user(), $owned);

        return redirect()
            ->back()
            ->with('status', 'profile-default-set');
    }

    public function switch(Request $request, Profile $profile): RedirectResponse
    {
        $owned = $this->current->findOwned($request->user(), $profile->id);
        $this->current->switchTo($request->user(), $owned->id);

        return redirect()
            ->back()
            ->with('status', 'profile-switched');
    }

    public function archive(Request $request, Profile $profile): RedirectResponse
    {
        $owned = $this->current->findOwned($request->user(), $profile->id);
        $this->profiles->archive($request->user(), $owned);

        return redirect()
            ->route('profiles.index')
            ->with('status', 'profile-archived');
    }

    public function restore(Request $request, Profile $profile): RedirectResponse
    {
        $owned = $this->current->findOwned($request->user(), $profile->id, allowArchived: true);
        $this->profiles->restore($request->user(), $owned);

        return redirect()
            ->route('profiles.index')
            ->with('status', 'profile-restored');
    }
}
