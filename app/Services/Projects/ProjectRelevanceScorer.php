<?php

namespace App\Services\Projects;

use App\Models\Project;
use App\Services\AI\JobAnalysis\SkillNormalizer;

/**
 * Deterministic, explainable project ↔ JD relevance scoring.
 * Does not call external AI providers.
 */
class ProjectRelevanceScorer
{
    public function __construct(
        private readonly SkillNormalizer $normalizer,
    ) {}

    /**
     * @param  array<string, mixed>  $analysis
     * @return array{
     *     score: float,
     *     matched_required_skills: list<string>,
     *     matched_preferred_skills: list<string>,
     *     matching_technologies: list<string>,
     *     relevant_features: list<string>,
     *     relevant_responsibilities: list<string>,
     *     explanation: string,
     *     components: array<string, float>
     * }
     */
    public function score(Project $project, array $analysis): array
    {
        $weights = $this->activeWeights($analysis);

        $required = $this->normalizer->normalizeList($analysis['required_skills'] ?? []);
        $preferred = $this->normalizer->normalizeList($analysis['preferred_skills'] ?? []);
        $techPool = $this->normalizer->normalizeList(array_merge(
            $required,
            $preferred,
            $analysis['frameworks_libraries'] ?? [],
            $analysis['databases'] ?? [],
            $analysis['tools_platforms'] ?? [],
            $analysis['technical_keywords'] ?? [],
        ));

        $projectTechs = $this->normalizer->normalizeList(
            $project->technologies->pluck('name')->all()
        );

        $matchedRequired = $this->intersectCanonical($required, $projectTechs);
        $matchedPreferred = $this->intersectCanonical($preferred, $projectTechs);
        $matchingTechnologies = $this->intersectCanonical($techPool, $projectTechs);

        $jdTextTokens = $this->jdRelevanceTokens($analysis);
        $features = $project->features->pluck('description')->all();
        $responsibilities = $project->responsibilities->pluck('description')->all();
        $relevantFeatures = $this->matchingTexts($features, $jdTextTokens);
        $relevantResponsibilities = $this->matchingTexts($responsibilities, $jdTextTokens);

        $components = [
            'required_skills' => $this->coverageRatio($matchedRequired, $required),
            'preferred_skills' => $this->coverageRatio($matchedPreferred, $preferred),
            'technologies' => $this->coverageRatio($matchingTechnologies, $techPool),
            'features_responsibilities' => $this->textCoverageRatio(
                count($relevantFeatures) + count($relevantResponsibilities),
                count($features) + count($responsibilities),
                count($jdTextTokens)
            ),
            'type_category' => $this->typeCategoryScore(
                (string) ($project->project_type ?? ''),
                (string) ($analysis['job_category'] ?? ''),
                (string) ($analysis['job_title'] ?? '')
            ),
        ];

        $weighted = 0.0;
        foreach ($weights as $key => $weight) {
            $weighted += ($components[$key] ?? 0.0) * $weight;
        }

        $score = round(max(0, min(100, $weighted * 100)), 1);

        return [
            'score' => $score,
            'matched_required_skills' => $matchedRequired,
            'matched_preferred_skills' => $matchedPreferred,
            'matching_technologies' => $matchingTechnologies,
            'relevant_features' => $relevantFeatures,
            'relevant_responsibilities' => $relevantResponsibilities,
            'explanation' => $this->explain($project, $score, $matchedRequired, $matchedPreferred, $matchingTechnologies),
            'components' => $components,
        ];
    }

    /**
     * @param  array<string, mixed>  $analysis
     * @return array<string, float>
     */
    private function activeWeights(array $analysis): array
    {
        $configured = config('ai.project_ranking.weights', []);
        $required = $this->normalizer->normalizeList($analysis['required_skills'] ?? []);
        $preferred = $this->normalizer->normalizeList($analysis['preferred_skills'] ?? []);
        $techPool = $this->normalizer->normalizeList(array_merge(
            $required,
            $preferred,
            $analysis['frameworks_libraries'] ?? [],
            $analysis['databases'] ?? [],
            $analysis['tools_platforms'] ?? [],
            $analysis['technical_keywords'] ?? [],
        ));
        $hasText = $this->jdRelevanceTokens($analysis) !== [];
        $hasType = trim((string) ($analysis['job_category'] ?? '')) !== ''
            || trim((string) ($analysis['job_title'] ?? '')) !== '';

        $active = [];
        foreach ($configured as $key => $weight) {
            $include = match ($key) {
                'required_skills' => $required !== [],
                'preferred_skills' => $preferred !== [],
                'technologies' => $techPool !== [],
                'features_responsibilities' => $hasText,
                'type_category' => $hasType,
                default => false,
            };

            if ($include && (float) $weight > 0) {
                $active[$key] = (float) $weight;
            }
        }

        $sum = array_sum($active);
        if ($sum <= 0) {
            return ['technologies' => 1.0];
        }

        foreach ($active as $key => $weight) {
            $active[$key] = $weight / $sum;
        }

        return $active;
    }

    /**
     * @param  list<string>  $left
     * @param  list<string>  $right
     * @return list<string>
     */
    private function intersectCanonical(array $left, array $right): array
    {
        $rightKeys = [];
        foreach ($right as $item) {
            $rightKeys[$this->normalizer->comparisonKey($item)] = $item;
        }

        $matched = [];
        foreach ($left as $item) {
            $key = $this->normalizer->comparisonKey($item);
            if (isset($rightKeys[$key])) {
                $matched[$key] = $item;
            }
        }

        return array_values($matched);
    }

    /**
     * @param  list<string>  $matched
     * @param  list<string>  $universe
     */
    private function coverageRatio(array $matched, array $universe): float
    {
        if ($universe === []) {
            return 0.0;
        }

        return count($matched) / count($universe);
    }

    private function textCoverageRatio(int $matchedTexts, int $projectTexts, int $jdTokenCount): float
    {
        if ($projectTexts === 0 || $jdTokenCount === 0) {
            return 0.0;
        }

        return min(1.0, $matchedTexts / max(1, min($projectTexts, 6)));
    }

    /**
     * @param  array<string, mixed>  $analysis
     * @return list<string>
     */
    private function jdRelevanceTokens(array $analysis): array
    {
        $parts = array_merge(
            $analysis['responsibilities'] ?? [],
            $analysis['technical_keywords'] ?? [],
            $analysis['required_skills'] ?? [],
            $analysis['preferred_skills'] ?? [],
            $analysis['frameworks_libraries'] ?? [],
            $analysis['tools_platforms'] ?? [],
            $analysis['databases'] ?? [],
        );

        $tokens = [];
        foreach ($parts as $part) {
            foreach ($this->tokenize((string) $part) as $token) {
                $tokens[$token] = $token;
            }
        }

        return array_values($tokens);
    }

    /**
     * @param  list<string>  $texts
     * @param  list<string>  $jdTokens
     * @return list<string>
     */
    private function matchingTexts(array $texts, array $jdTokens): array
    {
        if ($texts === [] || $jdTokens === []) {
            return [];
        }

        $jdSet = array_fill_keys($jdTokens, true);
        $matched = [];

        foreach ($texts as $text) {
            $trimmed = trim($text);
            if ($trimmed === '') {
                continue;
            }

            foreach ($this->tokenize($trimmed) as $token) {
                if (isset($jdSet[$token])) {
                    $matched[] = $trimmed;
                    break;
                }
            }
        }

        return array_values(array_unique($matched));
    }

    /**
     * @return list<string>
     */
    private function tokenize(string $text): array
    {
        $normalized = mb_strtolower($this->normalizer->normalize($text));
        $parts = preg_split('/[^a-z0-9.+#]+/u', $normalized) ?: [];

        $tokens = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if (mb_strlen($part) < 3) {
                continue;
            }
            // Alias-aware canonical key when the whole token is a known skill alias.
            $tokens[] = $this->normalizer->comparisonKey($part);
        }

        return array_values(array_unique($tokens));
    }

    private function typeCategoryScore(string $projectType, string $jobCategory, string $jobTitle): float
    {
        $typeTokens = $this->tokenize($projectType);
        $targetTokens = array_values(array_unique(array_merge(
            $this->tokenize($jobCategory),
            $this->tokenize($jobTitle)
        )));

        if ($typeTokens === [] || $targetTokens === []) {
            return 0.0;
        }

        $targetSet = array_fill_keys($targetTokens, true);
        $hits = 0;
        foreach ($typeTokens as $token) {
            if (isset($targetSet[$token])) {
                $hits++;
            }
        }

        return min(1.0, $hits / count($typeTokens));
    }

    /**
     * @param  list<string>  $matchedRequired
     * @param  list<string>  $matchedPreferred
     * @param  list<string>  $matchingTechnologies
     */
    private function explain(
        Project $project,
        float $score,
        array $matchedRequired,
        array $matchedPreferred,
        array $matchingTechnologies,
    ): string {
        if ($score <= 0 && $matchedRequired === [] && $matchingTechnologies === []) {
            return 'Limited overlap with the job requirements based on saved project technologies.';
        }

        $parts = [];
        if ($matchedRequired !== []) {
            $parts[] = 'required skills: '.implode(', ', array_slice($matchedRequired, 0, 4));
        }
        if ($matchedPreferred !== []) {
            $parts[] = 'preferred skills: '.implode(', ', array_slice($matchedPreferred, 0, 3));
        }
        if ($matchingTechnologies !== [] && $matchedRequired === []) {
            $parts[] = 'technologies: '.implode(', ', array_slice($matchingTechnologies, 0, 4));
        }

        $summary = $parts === []
            ? 'partial category or keyword overlap'
            : implode('; ', $parts);

        return sprintf(
            '%s scores %.1f/100 for this JD due to %s.',
            $project->name,
            $score,
            $summary
        );
    }
}
