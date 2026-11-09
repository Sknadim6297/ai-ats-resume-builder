<?php

namespace App\Services\AI\JobAnalysis;

use App\Models\JobAnalysis;
use App\Models\JobAnalysisSkillDecision;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Builds skill review state for a job analysis without mutating analysis_json.
 */
class SkillReviewService
{
    public function __construct(
        private readonly SkillComparisonService $comparison,
        private readonly SkillNormalizer $normalizer,
    ) {}

    /**
     * @return array{
     *     matched_required: list<array<string, mixed>>,
     *     matched_preferred: list<array<string, mixed>>,
     *     missing_required: list<array<string, mixed>>,
     *     missing_preferred: list<array<string, mixed>>,
     *     disclaimer: string
     * }
     */
    public function build(User $user, JobAnalysis $analysis): array
    {
        $payload = is_array($analysis->analysis_json) ? $analysis->analysis_json : [];
        $base = $this->comparison->compare($user, $payload, $analysis->profile);

        /** @var Collection<string, JobAnalysisSkillDecision> $decisions */
        $decisions = $analysis->skillDecisions()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy(fn (JobAnalysisSkillDecision $row) => $this->decisionKey(
                $row->normalized_skill_key,
                $row->requirement_type
            ));

        return [
            'matched_required' => $this->mapMatched(
                $base['matched_required'],
                JobAnalysisSkillDecision::REQUIREMENT_REQUIRED,
                $decisions
            ),
            'matched_preferred' => $this->mapMatched(
                $base['matched_preferred'],
                JobAnalysisSkillDecision::REQUIREMENT_PREFERRED,
                $decisions
            ),
            'missing_required' => $this->mapMissing(
                $base['missing_required'],
                JobAnalysisSkillDecision::REQUIREMENT_REQUIRED,
                $decisions
            ),
            'missing_preferred' => $this->mapMissing(
                $base['missing_preferred'],
                JobAnalysisSkillDecision::REQUIREMENT_PREFERRED,
                $decisions
            ),
            'disclaimer' => $base['disclaimer'],
        ];
    }

    /**
     * @param  list<string>  $skills
     * @param  Collection<string, JobAnalysisSkillDecision>  $decisions
     * @return list<array<string, mixed>>
     */
    private function mapMatched(array $skills, string $requirementType, Collection $decisions): array
    {
        $rows = [];

        foreach ($skills as $skill) {
            $canonical = $this->normalizer->normalize($skill);
            $key = $this->normalizer->comparisonKey($canonical);
            $decision = $decisions->get($this->decisionKey($key, $requirementType));

            $rows[] = [
                'name' => $canonical,
                'normalized_key' => $key,
                'requirement_type' => $requirementType,
                'match_source' => 'master_profile',
                'decision' => $decision?->decision ?? JobAnalysisSkillDecision::DECISION_PENDING,
                'confirmation_status' => $decision?->confirmation_status
                    ?? JobAnalysisSkillDecision::CONFIRMATION_NOT_CONFIRMED,
                'in_master_profile' => true,
                'actions_available' => false,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<string>  $skills
     * @param  Collection<string, JobAnalysisSkillDecision>  $decisions
     * @return list<array<string, mixed>>
     */
    private function mapMissing(array $skills, string $requirementType, Collection $decisions): array
    {
        $rows = [];

        foreach ($skills as $skill) {
            $canonical = $this->normalizer->normalize($skill);
            $key = $this->normalizer->comparisonKey($canonical);
            $decision = $decisions->get($this->decisionKey($key, $requirementType));
            $current = $decision?->decision ?? JobAnalysisSkillDecision::DECISION_PENDING;

            $rows[] = [
                'name' => $canonical,
                'normalized_key' => $key,
                'requirement_type' => $requirementType,
                'match_source' => match ($current) {
                    JobAnalysisSkillDecision::DECISION_RESUME_ONLY => 'resume_only_approval',
                    JobAnalysisSkillDecision::DECISION_IGNORE => 'ignored',
                    default => 'missing_from_profile',
                },
                'decision' => $current,
                'confirmation_status' => $decision?->confirmation_status
                    ?? JobAnalysisSkillDecision::CONFIRMATION_NOT_CONFIRMED,
                'in_master_profile' => false,
                'actions_available' => true,
            ];
        }

        return $rows;
    }

    private function decisionKey(string $normalizedKey, string $requirementType): string
    {
        return $normalizedKey.'|'.$requirementType;
    }
}
