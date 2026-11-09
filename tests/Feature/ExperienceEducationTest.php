<?php

namespace Tests\Feature;

use App\Models\Education;
use App\Models\User;
use App\Models\WorkExperience;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExperienceEducationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_work_experience(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('experience.store'), [
                'company_name' => 'Acme Corp',
                'job_title' => 'Backend Developer',
                'employment_type' => 'Full-time',
                'location' => 'Remote',
                'start_date' => '2022-01-01',
                'end_date' => '2023-01-01',
                'is_current' => false,
                'responsibilities' => ['Built APIs'],
                'achievements' => ['Reduced latency'],
                'technologies' => ['Laravel', 'MySQL'],
            ])
            ->assertRedirect(route('experience.index'));

        $this->assertDatabaseHas('work_experiences', [
            'user_id' => $user->id,
            'company_name' => 'Acme Corp',
        ]);
        $this->assertDatabaseCount('work_experience_technologies', 2);
    }

    public function test_current_role_clears_end_date(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('experience.store'), [
                'company_name' => 'Now Corp',
                'job_title' => 'Engineer',
                'employment_type' => 'Full-time',
                'start_date' => '2024-01-01',
                'end_date' => '2024-06-01',
                'is_current' => true,
                'responsibilities' => [],
                'achievements' => [],
                'technologies' => [],
            ])
            ->assertRedirect();

        $experience = WorkExperience::query()->firstOrFail();
        $this->assertTrue($experience->is_current);
        $this->assertNull($experience->end_date);
    }

    public function test_user_can_manage_education(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('education.store'), [
                'institution_name' => 'State University',
                'degree' => 'B.Tech',
                'field_of_study' => 'Computer Science',
                'start_year' => 2018,
                'end_year' => 2022,
                'grade' => '8.5 CGPA',
            ])
            ->assertRedirect(route('education.index'));

        $education = Education::query()->firstOrFail();

        $this->actingAs($user)
            ->put(route('education.update', $education), [
                'institution_name' => 'State University',
                'degree' => 'B.Tech CSE',
                'field_of_study' => 'Computer Science',
                'start_year' => 2018,
                'end_year' => 2022,
            ])
            ->assertRedirect(route('education.index'));

        $this->assertSame('B.Tech CSE', $education->fresh()->degree);
    }

    public function test_user_cannot_delete_another_users_education(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $this->actingAs($owner)->post(route('education.store'), [
            'institution_name' => 'Owner Uni',
            'degree' => 'BSc',
            'start_year' => 2015,
            'end_year' => 2019,
        ]);

        $education = Education::query()->firstOrFail();

        $this->actingAs($intruder)
            ->delete(route('education.destroy', $education))
            ->assertNotFound();
    }
}
