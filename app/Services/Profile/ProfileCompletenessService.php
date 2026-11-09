<?php

namespace App\Services\Profile;

use App\Models\User;

class ProfileCompletenessService
{
    use EnsuresUserProfile;

    /**
     * @return array{
     *     percentage: int,
     *     completed: int,
     *     total: int,
     *     sections: list<array{key:string,label:string,complete:bool,route:string,count:int}>
     * }
     */
    public function forUser(User $user): array
    {
        $profile = $user->profile;
        $skillsCount = $profile
            ? $profile->skills()->wherePivot('is_active', true)->count()
            : 0;
        $projectsCount = $user->projects()->where('is_active', true)->count();
        $experienceCount = $user->workExperiences()->count();
        $educationCount = $user->educations()->count();

        $personalComplete = $profile !== null
            && filled($profile->full_name)
            && filled($profile->email)
            && filled($profile->professional_summary);

        $sections = [
            [
                'key' => 'personal',
                'label' => 'Personal Information',
                'complete' => $personalComplete,
                'route' => 'master-profile.edit',
                'count' => $personalComplete ? 1 : 0,
            ],
            [
                'key' => 'skills',
                'label' => 'Skills',
                'complete' => $skillsCount > 0,
                'route' => 'skills.index',
                'count' => $skillsCount,
            ],
            [
                'key' => 'projects',
                'label' => 'Projects',
                'complete' => $projectsCount > 0,
                'route' => 'projects.index',
                'count' => $projectsCount,
            ],
            [
                'key' => 'experience',
                'label' => 'Experience',
                'complete' => $experienceCount > 0,
                'route' => 'experience.index',
                'count' => $experienceCount,
            ],
            [
                'key' => 'education',
                'label' => 'Education',
                'complete' => $educationCount > 0,
                'route' => 'education.index',
                'count' => $educationCount,
            ],
        ];

        $completed = collect($sections)->where('complete', true)->count();
        $total = count($sections);

        return [
            'percentage' => (int) round(($completed / max($total, 1)) * 100),
            'completed' => $completed,
            'total' => $total,
            'sections' => $sections,
        ];
    }
}
