<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_master_profile(): void
    {
        $this->get(route('master-profile.edit'))->assertRedirect(route('login'));
    }

    public function test_user_can_create_and_update_master_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('master-profile.update'), [
                'full_name' => 'Ada Lovelace',
                'headline' => 'Software Engineer',
                'email' => 'ada@example.com',
                'phone' => '1234567890',
                'location' => 'London',
                'linkedin_url' => 'https://linkedin.com/in/ada',
                'github_url' => 'https://github.com/ada',
                'portfolio_url' => 'https://ada.dev',
                'professional_summary' => 'Builder of analytical engines.',
                'years_of_experience' => 5,
                'additional_links' => [
                    ['label' => 'Blog', 'url' => 'https://blog.ada.dev'],
                ],
            ])
            ->assertRedirect(route('master-profile.edit'));

        $this->assertDatabaseHas('profiles', [
            'user_id' => $user->id,
            'full_name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
        ]);

        $this->assertSame(1, $user->profile()->count());
    }

    public function test_master_profile_requires_valid_email(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('master-profile.edit'))
            ->put(route('master-profile.update'), [
                'full_name' => 'Ada',
                'email' => 'not-an-email',
            ])
            ->assertSessionHasErrors('email');
    }
}
