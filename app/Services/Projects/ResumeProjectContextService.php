<?php

namespace App\Services\Projects;

use App\Models\JobAnalysis;
use App\Models\JobAnalysisProjectSelection;
use App\Models\User;

/**
 * Phase 7/8 contract: ordered selected projects for resume generation.
 */
class ResumeProjectContextService
{
    public function __construct(
        private readonly JobAnalysisProjectSelectionService $selections,
    ) {}

    /**
     * @param  array<string, mixed>|null  $presented
     * @return array{
     *     projects: list<array<string, mixed>>,
     *     has_saved_selection: bool,
     *     selection_source_summary: array<string, int>
     * }
     */
    public function forAnalysis(User $user, JobAnalysis $analysis, ?array $presented = null): array
    {
        if ($analysis->user_id !== $user->id) {
            abort(403);
        }

        $presented ??= $this->selections->present($user, $analysis);
        $projects = [];

        foreach ($presented['selected'] as $row) {
            $projects[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'project_type' => $row['project_type'] ?? null,
                'short_description' => $row['short_description'] ?? null,
                'detailed_description' => $row['detailed_description'] ?? null,
                'technologies' => $row['technologies'] ?? [],
                'features' => $row['features'] ?? [],
                'responsibilities' => $row['responsibilities'] ?? [],
                'relevance_score' => $row['relevance_score'] ?? null,
                'explanation' => $row['explanation'] ?? null,
                'selection_source' => $row['selection_source'] ?? JobAnalysisProjectSelection::SOURCE_MANUAL,
                'display_order' => $row['display_order'] ?? count($projects),
            ];
        }

        $summary = [
            JobAnalysisProjectSelection::SOURCE_RECOMMENDATION => 0,
            JobAnalysisProjectSelection::SOURCE_MANUAL => 0,
        ];
        foreach ($projects as $project) {
            $source = $project['selection_source'];
            $summary[$source] = ($summary[$source] ?? 0) + 1;
        }

        return [
            'projects' => $projects,
            'has_saved_selection' => (bool) $presented['has_saved_selection'],
            'selection_source_summary' => $summary,
        ];
    }
}
