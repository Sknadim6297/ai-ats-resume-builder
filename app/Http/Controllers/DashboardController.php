<?php

namespace App\Http\Controllers;

use App\Services\Dashboard\DashboardStatsService;
use App\Services\Profile\ProfileCompletenessService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardStatsService $stats,
        private readonly ProfileCompletenessService $completeness,
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user()->load('profile');

        return Inertia::render('Dashboard', [
            'stats' => $this->stats->forUser($user),
            'completeness' => $this->completeness->forUser($user),
            'userName' => $user->name,
        ]);
    }
}
