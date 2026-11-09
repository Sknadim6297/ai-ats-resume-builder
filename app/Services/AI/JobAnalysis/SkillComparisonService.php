<?php

namespace App\Services\AI\JobAnalysis;

use App\Models\Profile;
use App\Models\User;

/**
 * Read-only comparison between JD skills and verified profile skills.
 * Does not mutate profile data (Phase 5 owns approvals/updates).
 */
class SkillComparisonService
{
    public function __construct(
        private readonly SkillNormalizer $normalizer,
    ) {}

    /**
     * @param  array<string, mixed>  $analysis
     * @return array{
     *     profile_skills: list<string>,
     *     matched_required: list<string>,
     *     missing_required: list<string>,
     *     matched_preferred: list<string>,
     *     missing_preferred: list<string>,
     *     disclaimer: string
     * }
     */
    public function compare(User $user, array $analysis, ?Profile $profile = null): array
    {
        $profile ??= $user->profile;
        $profileSkills = [];
        if ($profile && $profile->user_id === $user->id) {
            // Only user-confirmed skills count toward matching / resume approval.
            $profileSkills = $profile->skills()
                ->wherePivot('is_active', true)
                ->wherePivot('is_verified', true)
                ->pluck('skills.name')
                ->all();
        }

        $profileKeys = [];
        foreach ($this->normalizer->normalizeList($profileSkills) as $skill) {
            $profileKeys[$this->normalizer->comparisonKey($skill)] = $skill;
        }

        $required = $this->normalizer->normalizeList($analysis['required_skills'] ?? []);
        $preferred = $this->normalizer->normalizeList($analysis['preferred_skills'] ?? []);

        return [
            'profile_skills' => array_values($profileKeys),
            'matched_required' => $this->intersect($required, $profileKeys),
            'missing_required' => $this->diff($required, $profileKeys),
            'matched_preferred' => $this->intersect($preferred, $profileKeys),
            'missing_preferred' => $this->diff($preferred, $profileKeys),
            'disclaimer' => 'Missing skills means they were not found in your saved Master Profile skills. This does not prove you lack the skill.',
        ];
    }

    /**
     * @param  list<string>  $skills
     * @param  array<string, string>  $profileKeys
     * @return list<string>
     */
    private function intersect(array $skills, array $profileKeys): array
    {
        $matched = [];
        foreach ($skills as $skill) {
            $key = $this->normalizer->comparisonKey($skill);
            if (isset($profileKeys[$key])) {
                $matched[] = $skill;
            }
        }

        return $matched;
    }

    /**
     * @param  list<string>  $skills
     * @param  array<string, string>  $profileKeys
     * @return list<string>
     */
    private function diff(array $skills, array $profileKeys): array
    {
        $missing = [];
        foreach ($skills as $skill) {
            $key = $this->normalizer->comparisonKey($skill);
            if (! isset($profileKeys[$key])) {
                $missing[] = $skill;
            }
        }

        return $missing;
    }
}
