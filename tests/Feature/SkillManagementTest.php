<?php

namespace Tests\Feature;

use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SkillManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_add_skill(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('skills.store'), [
                'name' => 'Laravel',
                'category' => 'Backend',
                'proficiency' => 5,
                'years_experience' => 3,
                'is_active' => true,
            ])
            ->assertRedirect(route('skills.index'));

        $this->assertDatabaseHas('skills', ['slug' => 'laravel', 'name' => 'Laravel']);
        $this->assertDatabaseCount('profile_skills', 1);
    }

    public function test_duplicate_skill_on_profile_is_rejected(): void
    {
        $user = User::factory()->create();

        $payload = [
            'name' => 'React.js',
            'category' => 'Frontend',
            'proficiency' => 4,
        ];

        $this->actingAs($user)->post(route('skills.store'), $payload)->assertRedirect();
        $this->actingAs($user)
            ->from(route('skills.index'))
            ->post(route('skills.store'), $payload)
            ->assertSessionHasErrors('name');
    }

    public function test_user_can_delete_own_skill(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('skills.store'), [
            'name' => 'MySQL',
            'category' => 'Databases',
        ]);

        $skill = Skill::query()->where('slug', 'mysql')->firstOrFail();

        $this->actingAs($user)
            ->delete(route('skills.destroy', $skill->id))
            ->assertRedirect(route('skills.index'));

        $this->assertDatabaseMissing('profile_skills', ['skill_id' => $skill->id]);
    }

    public function test_user_cannot_modify_another_users_skill_attachment(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $this->actingAs($owner)->post(route('skills.store'), [
            'name' => 'PHP',
            'category' => 'Programming Languages',
        ]);

        $skill = Skill::query()->where('slug', 'php')->firstOrFail();

        $this->actingAs($intruder)
            ->delete(route('skills.destroy', $skill->id))
            ->assertNotFound();
    }
}
