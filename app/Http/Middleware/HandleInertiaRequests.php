<?php

namespace App\Http\Middleware;

use App\Services\Profile\CurrentProfileService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $currentProfile = null;
        $profileOptions = [];

        if ($request->user()) {
            $current = app(CurrentProfileService::class)->resolve($request->user());
            $currentProfile = [
                'id' => $current->id,
                'name' => $current->name,
                'headline' => $current->headline,
                'is_default' => (bool) $current->is_default,
            ];
            $profileOptions = $request->user()->profiles()
                ->whereNull('archived_at')
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(['id', 'name', 'headline', 'is_default'])
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'headline' => $p->headline,
                    'is_default' => (bool) $p->is_default,
                ])
                ->values()
                ->all();
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'currentProfile' => $currentProfile,
            'profileOptions' => $profileOptions,
            // Alias kept for any pages still reading the older prop name.
            'profileSelector' => $profileOptions,
        ];
    }
}
