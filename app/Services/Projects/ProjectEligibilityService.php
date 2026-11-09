<?php

namespace App\Services\Projects;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class ProjectEligibilityService
{
    /**
     * Active, resume-eligible projects owned by the user.
     *
     * @return Collection<int, Project>
     */
    public function eligibleForUser(User $user, ?int $profileId = null): Collection
    {
        return Project::query()
            ->where('user_id', $user->id)
            ->when($profileId, fn ($q) => $q->where('profile_id', $profileId))
            ->where('is_active', true)
            ->where('include_in_resume', true)
            ->with(['technologies', 'features', 'responsibilities'])
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  list<int>  $projectIds
     * @return Collection<int, Project>
     */
    public function eligibleOwnedByIds(User $user, array $projectIds, ?int $profileId = null): Collection
    {
        $ids = array_values(array_unique(array_map('intval', $projectIds)));

        if ($ids === []) {
            return new Collection;
        }

        return Project::query()
            ->where('user_id', $user->id)
            ->when($profileId, fn ($q) => $q->where('profile_id', $profileId))
            ->where('is_active', true)
            ->where('include_in_resume', true)
            ->whereIn('id', $ids)
            ->with(['technologies', 'features', 'responsibilities'])
            ->get()
            ->keyBy('id');
    }
}
