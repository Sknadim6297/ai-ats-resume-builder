<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Inventory System',
            'short_description' => 'Stock tracking web app',
            'detailed_description' => 'Tracks inventory and invoices.',
            'project_type' => 'Web Application',
            'github_url' => 'https://github.com/example/inventory',
            'is_ongoing' => false,
            'is_active' => true,
            'include_in_resume' => true,
            'technologies' => ['Laravel', 'MySQL', 'React.js'],
            'features' => ['Auth', 'Reports'],
            'responsibilities' => ['Built APIs', 'Designed schema'],
        ], $overrides);
    }

    public function test_user_can_create_project_with_structured_children(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('projects.store'), $this->payload())
            ->assertRedirect(route('projects.index'));

        $project = Project::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertDatabaseCount('project_technologies', 3);
        $this->assertDatabaseCount('project_features', 2);
        $this->assertDatabaseCount('project_responsibilities', 2);
        $this->assertSame('Inventory System', $project->name);
    }

    public function test_project_requires_technologies(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('projects.create'))
            ->post(route('projects.store'), $this->payload(['technologies' => []]))
            ->assertSessionHasErrors('technologies');
    }

    public function test_user_cannot_edit_another_users_project(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $this->actingAs($owner)->post(route('projects.store'), $this->payload());
        $project = Project::query()->firstOrFail();

        $this->actingAs($intruder)
            ->get(route('projects.edit', $project))
            ->assertNotFound();
    }
}
