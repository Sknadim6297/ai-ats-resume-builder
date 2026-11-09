<?php

namespace App\Services\AI\JobAnalysis;

use App\Models\JobAnalysis;
use App\Models\JobAnalysisSkillDecision;
use App\Models\User;

/**
 * Phase 7/8 contract: expose approved skill sets for a job analysis
 * without generating resumes.
 */
class ResumeSkillContextService
{
    public function __construct(
        private readonly SkillComparisonService $comparison,
        private readonly SkillNormalizer $normalizer,
    ) {}

    /**
     * @return array{
     *     verified_master_profile_skills: list<string>,
     *     approved_resume_only_skills: list<string>,
     *     ignored_skills: list<string>,
     *     pending_skills: list<string>,
     *     original_required_skills: list<string>,
     *     original_preferred_skills: list<string>
     * }
     */
    public function forAnalysis(User $user, JobAnalysis $analysis): array
    {
        if ($analysis->user_id !== $user->id) {
            abort(403);
        }

        $payload = is_array($analysis->analysis_json) ? $analysis->analysis_json : [];
        $profile = $analysis->profile ?? $user->profile;
        $base = $this->comparison->compare($user, $payload, $profile);

        $required = $this->normalizer->normalizeList($payload['required_skills'] ?? []);
        $preferred = $this->normalizer->normalizeList($payload['preferred_skills'] ?? []);
        $allJd = $this->normalizer->normalizeList(array_merge($required, $preferred));

        $profileKeys = [];
        foreach ($base['profile_skills'] as $skill) {
            $profileKeys[$this->normalizer->comparisonKey($skill)] = true;
        }

        $decisionByKey = [];
        foreach ($analysis->skillDecisions()->where('user_id', $user->id)->get() as $row) {
            $existing = $decisionByKey[$row->normalized_skill_key] ?? JobAnalysisSkillDecision::DECISION_PENDING;
            $decisionByKey[$row->normalized_skill_key] = $this->preferDecision($existing, $row->decision);
        }

        $resumeOnly = [];
        $ignored = [];
        $pending = [];

        foreach ($allJd as $skill) {
            $key = $this->normalizer->comparisonKey($skill);
            $decision = $decisionByKey[$key] ?? JobAnalysisSkillDecision::DECISION_PENDING;
            $inProfile = isset($profileKeys[$key]);

            if ($decision === JobAnalysisSkillDecision::DECISION_RESUME_ONLY) {
                $resumeOnly[] = $skill;
                continue;
            }

            if ($decision === JobAnalysisSkillDecision::DECISION_IGNORE) {
                $ignored[] = $skill;
                continue;
            }

            if ($decision === JobAnalysisSkillDecision::DECISION_ADD_TO_PROFILE || $inProfile) {
                continue;
            }

            $pending[] = $skill;
        }

        return [
            'verified_master_profile_skills' => $base['profile_skills'],
            'approved_resume_only_skills' => array_values(array_unique($resumeOnly)),
            'ignored_skills' => array_values(array_unique($ignored)),
            'pending_skills' => array_values(array_unique($pending)),
            'original_required_skills' => $required,
            'original_preferred_skills' => $preferred,
        ];
    }

    private function preferDecision(string $current, string $incoming): string
    {
        $rank = [
            JobAnalysisSkillDecision::DECISION_PENDING => 0,
            JobAnalysisSkillDecision::DECISION_IGNORE => 1,
            JobAnalysisSkillDecision::DECISION_RESUME_ONLY => 2,
            JobAnalysisSkillDecision::DECISION_ADD_TO_PROFILE => 3,
        ];

        return ($rank[$incoming] ?? 0) >= ($rank[$current] ?? 0) ? $incoming : $current;
    }
}
