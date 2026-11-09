<?php

namespace App\Services\Profile;

use App\Models\Project;
use App\Models\Skill;
use App\Models\User;
use App\Services\Profile\CurrentProfileService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ProjectService
{
    /**
     * @param  array{search?:string,type?:string,technology?:string,status?:string}  $filters
     */
    public function listForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        $profile = app(CurrentProfileService::class)->resolve($user);

        return Project::query()
            ->where('user_id', $user->id)
            ->where('profile_id', $profile->id)
            ->with(['technologies', 'features', 'responsibilities'])
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $inner) use ($search) {
                    $inner->where('name', 'like', '%'.$search.'%')
                        ->orWhere('short_description', 'like', '%'.$search.'%');
                });
            })
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('project_type', $type))
            ->when(($filters['status'] ?? null) === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn (Builder $query) => $query->where('is_active', false))
            ->when($filters['technology'] ?? null, function (Builder $query, string $technology) {
                $query->whereHas('technologies', fn (Builder $tech) => $tech->where('name', 'like', '%'.$technology.'%'));
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();
    }

    public function findOwned(User $user, int $projectId): Project
    {
        return Project::query()
            ->where('user_id', $user->id)
            ->with(['technologies', 'features', 'responsibilities'])
            ->findOrFail($projectId);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): Project
    {
        return DB::transaction(function () use ($user, $data) {
            $profile = app(CurrentProfileService::class)->resolve($user);
            $project = Project::query()->create([
                'user_id' => $user->id,
                'profile_id' => $profile->id,
                ...$this->projectAttributes($data),
            ]);

            $this->syncChildren($project, $data);

            return $project->load(['technologies', 'features', 'responsibilities']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, Project $project, array $data): Project
    {
        $this->assertOwned($user, $project);

        return DB::transaction(function () use ($project, $data) {
            $project->update($this->projectAttributes($data));
            $project->technologies()->delete();
            $project->features()->delete();
            $project->responsibilities()->delete();
            $this->syncChildren($project, $data);

            return $project->fresh()->load(['technologies', 'features', 'responsibilities']);
        });
    }

    public function delete(User $user, Project $project): void
    {
        $this->assertOwned($user, $project);
        $project->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function projectAttributes(array $data): array
    {
        $isOngoing = (bool) ($data['is_ongoing'] ?? false);

        return [
            'name' => trim($data['name']),
            'short_description' => trim($data['short_description']),
            'detailed_description' => $data['detailed_description'] ?? null,
            'project_type' => $data['project_type'],
            'github_url' => $data['github_url'] ?? null,
            'live_url' => $data['live_url'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $isOngoing ? null : ($data['end_date'] ?? null),
            'is_ongoing' => $isOngoing,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'include_in_resume' => (bool) ($data['include_in_resume'] ?? true),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncChildren(Project $project, array $data): void
    {
        foreach (array_values(array_unique(array_filter(array_map('trim', $data['technologies'] ?? [])))) as $index => $name) {
            $skill = Skill::query()->where('slug', Skill::makeSlug($name))->first();
            $project->technologies()->create([
                'name' => $name,
                'skill_id' => $skill?->id,
                'sort_order' => $index,
            ]);
        }

        foreach (array_values(array_unique(array_filter(array_map('trim', $data['features'] ?? [])))) as $index => $description) {
            $project->features()->create([
                'description' => $description,
                'sort_order' => $index,
            ]);
        }

        foreach (array_values(array_unique(array_filter(array_map('trim', $data['responsibilities'] ?? [])))) as $index => $description) {
            $project->responsibilities()->create([
                'description' => $description,
                'sort_order' => $index,
            ]);
        }
    }

    private function assertOwned(User $user, Project $project): void
    {
        if ($project->user_id !== $user->id) {
            abort(403);
        }
    }
}
