<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Profile\ProfileCompletenessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfileCompletenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_zero_completeness_for_empty_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('completeness.percentage', 0)
                ->where('completeness.completed', 0)
                ->has('completeness.sections', 5)
            );
    }

    public function test_completeness_increases_with_saved_sections(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('master-profile.update'), [
            'full_name' => 'Test User',
            'email' => 'test@example.com',
            'professional_summary' => 'Experienced developer.',
        ]);

        $this->actingAs($user)->post(route('skills.store'), [
            'name' => 'Laravel',
            'category' => 'Backend',
        ]);

        $result = app(ProfileCompletenessService::class)->forUser($user->fresh());

        $this->assertSame(2, $result['completed']);
        $this->assertSame(40, $result['percentage']);
        $this->assertTrue(collect($result['sections'])->firstWhere('key', 'personal')['complete']);
        $this->assertTrue(collect($result['sections'])->firstWhere('key', 'skills')['complete']);
    }
}
