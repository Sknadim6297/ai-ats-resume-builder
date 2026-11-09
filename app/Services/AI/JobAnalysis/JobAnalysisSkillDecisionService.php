<?php

namespace App\Services\AI\JobAnalysis;

use App\Models\JobAnalysis;
use App\Models\JobAnalysisSkillDecision;
use App\Models\User;
use App\Services\Profile\SkillManagerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JobAnalysisSkillDecisionService
{
    public function __construct(
        private readonly SkillNormalizer $normalizer,
        private readonly SkillManagerService $skills,
    ) {}

    /**
     * @param  array{
     *     skill_name: string,
     *     requirement_type: string,
     *     decision: string,
     *     confirmed?: bool
     * }  $input
     */
    public function decide(User $user, JobAnalysis $analysis, array $input): JobAnalysisSkillDecision
    {
        if ($analysis->user_id !== $user->id) {
            abort(403);
        }

        if (! $analysis->isCompleted() || ! is_array($analysis->analysis_json)) {
            throw ValidationException::withMessages([
                'decision' => 'Skill decisions are only available for completed analyses.',
            ]);
        }

        $requirementType = $input['requirement_type'];
        $decision = $input['decision'];
        $canonical = $this->normalizer->normalize($input['skill_name']);
        $key = $this->normalizer->comparisonKey($canonical);

        if ($canonical === '' || $key === '') {
            throw ValidationException::withMessages([
                'skill_name' => 'Skill name is invalid.',
            ]);
        }

        $this->assertSkillBelongsToAnalysis($analysis, $canonical, $requirementType);
        $this->assertConfirmation($decision, (bool) ($input['confirmed'] ?? false));

        return DB::transaction(function () use ($user, $analysis, $canonical, $key, $requirementType, $decision) {
            if ($decision === JobAnalysisSkillDecision::DECISION_ADD_TO_PROFILE) {
                $profile = $analysis->profile;
                $this->skills->ensureAttached($user, $canonical, [
                    'category' => null,
                    'proficiency' => 3,
                    'is_active' => true,
                    'source' => 'jd_approved',
                ], $profile);
            }

            $confirmation = match ($decision) {
                JobAnalysisSkillDecision::DECISION_ADD_TO_PROFILE,
                JobAnalysisSkillDecision::DECISION_RESUME_ONLY => JobAnalysisSkillDecision::CONFIRMATION_USER_CONFIRMED,
                JobAnalysisSkillDecision::DECISION_IGNORE,
                JobAnalysisSkillDecision::DECISION_PENDING => JobAnalysisSkillDecision::CONFIRMATION_NOT_REQUIRED,
                default => JobAnalysisSkillDecision::CONFIRMATION_NOT_CONFIRMED,
            };

            return JobAnalysisSkillDecision::query()->updateOrCreate(
                [
                    'job_analysis_id' => $analysis->id,
                    'normalized_skill_key' => $key,
                    'requirement_type' => $requirementType,
                ],
                [
                    'user_id' => $user->id,
                    'canonical_skill_name' => $canonical,
                    'decision' => $decision,
                    'confirmation_status' => $confirmation,
                    'decided_at' => now(),
                ]
            )->fresh();
        });
    }

    private function assertSkillBelongsToAnalysis(JobAnalysis $analysis, string $canonical, string $requirementType): void
    {
        $field = $requirementType === JobAnalysisSkillDecision::REQUIREMENT_REQUIRED
            ? 'required_skills'
            : 'preferred_skills';

        $skills = $this->normalizer->normalizeList($analysis->analysis_json[$field] ?? []);
        $keys = array_map(fn (string $skill) => $this->normalizer->comparisonKey($skill), $skills);

        if (! in_array($this->normalizer->comparisonKey($canonical), $keys, true)) {
            throw ValidationException::withMessages([
                'skill_name' => 'This skill is not part of the original job analysis requirements.',
            ]);
        }
    }

    private function assertConfirmation(string $decision, bool $confirmed): void
    {
        $needsConfirmation = in_array($decision, [
            JobAnalysisSkillDecision::DECISION_ADD_TO_PROFILE,
            JobAnalysisSkillDecision::DECISION_RESUME_ONLY,
        ], true);

        if ($needsConfirmation && ! $confirmed) {
            throw ValidationException::withMessages([
                'confirmed' => 'Please confirm that you genuinely possess this skill before approving it.',
            ]);
        }
    }
}
