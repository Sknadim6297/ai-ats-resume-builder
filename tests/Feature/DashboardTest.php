<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_dashboard(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->has('stats')
                ->has('completeness')
                ->where('stats.resumes_generated', 0)
                ->where('stats.job_analyses', 0)
                ->where('stats.skills', 0)
                ->where('stats.projects', 0)
                ->where('stats.recent_activity', [])
                ->where('completeness.percentage', 0)
            );
    }

    public function test_dashboard_reports_real_skill_counts(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('skills.store'), [
            'name' => 'Laravel',
            'category' => 'Backend',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('stats.skills', 1)
                ->where('stats.projects', 0)
            );
    }
}
