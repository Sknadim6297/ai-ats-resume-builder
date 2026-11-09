<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\Project;
use App\Models\Skill;
use App\Models\User;
use App\Services\Profile\CurrentProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_multiple_profiles(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('profiles.store'), [
                'name' => 'Laravel Developer',
                'full_name' => $user->name,
                'email' => $user->email,
            ])
            ->assertRedirect(route('profiles.index'));

        $this->actingAs($user)
            ->post(route('profiles.store'), [
                'name' => 'React Native Developer',
                'full_name' => $user->name,
                'email' => $user->email,
            ])
            ->assertRedirect(route('profiles.index'));

        $this->assertSame(3, Profile::query()->where('user_id', $user->id)->count());
        $this->assertSame(1, Profile::query()->where('user_id', $user->id)->where('is_default', true)->count());
    }

    public function test_duplicate_profile_copies_scoped_data(): void
    {
        $user = User::factory()->create();
        $profile = $user->profiles()->first();

        $skill = Skill::query()->create(['name' => 'Laravel', 'slug' => 'laravel']);
        $profile->skills()->attach($skill->id, [
            'proficiency' => 5,
            'is_verified' => true,
            'is_active' => true,
            'source' => 'manual',
        ]);

        Project::query()->create([
            'user_id' => $user->id,
            'profile_id' => $profile->id,
            'name' => 'ATS App',
            'short_description' => 'Resume builder',
            'project_type' => 'Web Application',
            'is_active' => true,
            'include_in_resume' => true,
        ]);

        $this->actingAs($user)
            ->post(route('profiles.duplicate', $profile))
            ->assertRedirect();

        $copy = Profile::query()->where('user_id', $user->id)->where('id', '!=', $profile->id)->latest('id')->first();
        $this->assertNotNull($copy);
        $this->assertSame(1, $copy->skills()->count());
        $this->assertSame(1, $copy->projects()->count());
        $this->assertFalse((bool) $copy->is_default);
    }

    public function test_switch_profile_updates_session_scope(): void
    {
        $user = User::factory()->create();
        $default = $user->profiles()->first();

        $second = Profile::query()->create([
            'user_id' => $user->id,
            'name' => 'AI Developer',
            'full_name' => $user->name,
            'email' => $user->email,
            'is_default' => false,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->withSession([CurrentProfileService::SESSION_KEY => $second->id])
            ->post(route('skills.store'), [
                'name' => 'Python',
                'category' => 'Backend',
                'proficiency' => 4,
            ])
            ->assertRedirect(route('skills.index'));

        $this->assertTrue($second->fresh()->skills()->where('skills.slug', 'python')->exists());
        $this->assertFalse($default->fresh()->skills()->where('skills.slug', 'python')->exists());
    }

    public function test_archive_and_restore_profile(): void
    {
        $user = User::factory()->create();
        $default = $user->profiles()->first();

        $extra = Profile::query()->create([
            'user_id' => $user->id,
            'name' => 'MERN Stack',
            'full_name' => $user->name,
            'email' => $user->email,
            'is_default' => false,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->post(route('profiles.archive', $extra))
            ->assertRedirect(route('profiles.index'));

        $this->assertNotNull($extra->fresh()->archived_at);

        $this->actingAs($user)
            ->post(route('profiles.restore', $extra))
            ->assertRedirect();

        $this->assertNull($extra->fresh()->archived_at);
        $this->assertTrue((bool) $default->fresh()->is_default);
    }

    public function test_only_one_default_profile_per_user(): void
    {
        $user = User::factory()->create();
        $first = $user->profiles()->first();

        $second = Profile::query()->create([
            'user_id' => $user->id,
            'name' => 'Full Stack',
            'full_name' => $user->name,
            'email' => $user->email,
            'is_default' => false,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->post(route('profiles.set-default', $second))
            ->assertRedirect();

        $this->assertFalse((bool) $first->fresh()->is_default);
        $this->assertTrue((bool) $second->fresh()->is_default);
        $this->assertSame(1, Profile::query()->where('user_id', $user->id)->where('is_default', true)->count());
    }

    public function test_cross_user_profile_isolation(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $profile = $owner->profiles()->first();

        $this->actingAs($intruder)
            ->put(route('profiles.update', $profile), [
                'name' => 'Hacked',
                'full_name' => 'Hacked',
                'email' => 'hacked@example.com',
            ])
            ->assertNotFound();

        $this->actingAs($intruder)
            ->post(route('profiles.switch', $profile))
            ->assertNotFound();
    }

    public function test_projects_are_profile_scoped(): void
    {
        $user = User::factory()->create();
        $a = $user->profiles()->first();
        $b = Profile::query()->create([
            'user_id' => $user->id,
            'name' => 'Mobile',
            'full_name' => $user->name,
            'email' => $user->email,
            'is_default' => false,
            'is_active' => true,
        ]);

        Project::query()->create([
            'user_id' => $user->id,
            'profile_id' => $a->id,
            'name' => 'Profile A Project',
            'short_description' => 'A',
            'project_type' => 'Web Application',
            'is_active' => true,
            'include_in_resume' => true,
        ]);

        $this->actingAs($user)
            ->withSession([CurrentProfileService::SESSION_KEY => $b->id])
            ->get(route('projects.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Projects/Index')
                ->where('projects.data', []));
    }
}
