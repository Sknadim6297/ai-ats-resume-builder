<?php

namespace App\Services\Projects;

use App\Models\JobAnalysis;
use App\Models\User;

class ProjectRankingService
{
    public function __construct(
        private readonly ProjectEligibilityService $eligibility,
        private readonly ProjectRelevanceScorer $scorer,
    ) {}

    /**
     * @return array{
     *     ranked: list<array<string, mixed>>,
     *     recommended: list<array<string, mixed>>,
     *     recommendation_count: int,
     *     low_confidence: bool,
     *     empty_reason: string|null
     * }
     */
    public function rankForAnalysis(User $user, JobAnalysis $analysis): array
    {
        if ($analysis->user_id !== $user->id) {
            abort(403);
        }

        $payload = is_array($analysis->analysis_json) ? $analysis->analysis_json : [];
        $projects = $this->eligibility->eligibleForUser($user, $analysis->profile_id);

        if ($projects->isEmpty()) {
            return [
                'ranked' => [],
                'recommended' => [],
                'recommendation_count' => $this->recommendationCount(),
                'low_confidence' => true,
                'empty_reason' => 'No active, resume-eligible projects are available to rank.',
            ];
        }

        $ranked = $projects
            ->map(function ($project) use ($payload) {
                $result = $this->scorer->score($project, $payload);

                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'project_type' => $project->project_type,
                    'short_description' => $project->short_description,
                    'detailed_description' => $project->detailed_description,
                    'technologies' => $project->technologies->pluck('name')->values()->all(),
                    'features' => $project->features->pluck('description')->values()->all(),
                    'responsibilities' => $project->responsibilities->pluck('description')->values()->all(),
                    'relevance_score' => $result['score'],
                    'matched_required_skills' => $result['matched_required_skills'],
                    'matched_preferred_skills' => $result['matched_preferred_skills'],
                    'matching_technologies' => $result['matching_technologies'],
                    'relevant_features' => $result['relevant_features'],
                    'relevant_responsibilities' => $result['relevant_responsibilities'],
                    'explanation' => $result['explanation'],
                    'is_recommended' => false,
                ];
            })
            ->sort(function (array $a, array $b) {
                if ($a['relevance_score'] !== $b['relevance_score']) {
                    return $b['relevance_score'] <=> $a['relevance_score'];
                }

                $nameCmp = strcmp(mb_strtolower($a['name']), mb_strtolower($b['name']));
                if ($nameCmp !== 0) {
                    return $nameCmp;
                }

                return $a['id'] <=> $b['id'];
            })
            ->values();

        $count = min($this->recommendationCount(), $ranked->count());
        $threshold = (float) config('ai.project_ranking.low_confidence_score', 25);

        $recommended = $ranked
            ->take($count)
            ->filter(fn (array $row) => $row['relevance_score'] >= $threshold)
            ->values();

        // If everything is below threshold, still surface top scored items as low-confidence suggestions
        // but mark the state so UI can warn — do not invent projects.
        $lowConfidence = $recommended->isEmpty();
        if ($lowConfidence && $ranked->isNotEmpty()) {
            $recommended = $ranked->take(min(1, $count))->values();
        }

        $recommendedIds = $recommended->pluck('id')->all();
        $ranked = $ranked->map(function (array $row) use ($recommendedIds) {
            $row['is_recommended'] = in_array($row['id'], $recommendedIds, true);

            return $row;
        })->all();

        return [
            'ranked' => $ranked,
            'recommended' => $recommended->all(),
            'recommendation_count' => $count,
            'low_confidence' => $lowConfidence || (
                $recommended->isNotEmpty()
                && $recommended->avg(fn (array $row) => $row['relevance_score']) < $threshold
            ),
            'empty_reason' => null,
        ];
    }

    private function recommendationCount(): int
    {
        $default = (int) config('ai.project_ranking.default_recommendation_count', 3);
        $min = (int) config('ai.project_ranking.min_recommendation_count', 2);
        $max = (int) config('ai.project_ranking.max_recommendation_count', 5);

        return max($min, min($max, $default));
    }
}
