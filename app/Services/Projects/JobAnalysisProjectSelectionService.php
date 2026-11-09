<?php

namespace App\Services\Projects;

use App\Models\JobAnalysis;
use App\Models\JobAnalysisProjectSelection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JobAnalysisProjectSelectionService
{
    public function __construct(
        private readonly ProjectEligibilityService $eligibility,
        private readonly ProjectRankingService $ranking,
    ) {}

    /**
     * Present ranking + current selection without overwriting manual saves.
     *
     * @return array<string, mixed>
     */
    public function present(User $user, JobAnalysis $analysis): array
    {
        if ($analysis->user_id !== $user->id) {
            abort(403);
        }

        $ranking = $this->ranking->rankForAnalysis($user, $analysis);
        $saved = JobAnalysisProjectSelection::query()
            ->where('user_id', $user->id)
            ->where('job_analysis_id', $analysis->id)
            ->where('selected', true)
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();

        $hasSavedSelection = $saved->isNotEmpty();
        $rankedById = collect($ranking['ranked'])->keyBy('id');

        if ($hasSavedSelection) {
            $selected = [];
            foreach ($saved as $row) {
                $ranked = $rankedById->get($row->project_id);
                if (! $ranked) {
                    continue; // project no longer eligible
                }
                $selected[] = array_merge($ranked, [
                    'selected' => true,
                    'display_order' => $row->display_order,
                    'selection_source' => $row->selection_source,
                ]);
            }
        } else {
            $selected = [];
            foreach ($ranking['recommended'] as $index => $row) {
                $selected[] = array_merge($row, [
                    'selected' => true,
                    'display_order' => $index,
                    'selection_source' => JobAnalysisProjectSelection::SOURCE_RECOMMENDATION,
                ]);
            }
        }

        $selectedIds = collect($selected)->pluck('id')->all();

        $available = collect($ranking['ranked'])
            ->map(function (array $row) use ($selectedIds, $saved) {
                $savedRow = $saved->firstWhere('project_id', $row['id']);

                return array_merge($row, [
                    'selected' => in_array($row['id'], $selectedIds, true),
                    'selection_source' => $savedRow?->selection_source
                        ?? ($row['is_recommended']
                            ? JobAnalysisProjectSelection::SOURCE_RECOMMENDATION
                            : JobAnalysisProjectSelection::SOURCE_MANUAL),
                ]);
            })
            ->all();

        return [
            'ranked' => $ranking['ranked'],
            'recommended' => $ranking['recommended'],
            'available' => $available,
            'selected' => $selected,
            'has_saved_selection' => $hasSavedSelection,
            'recommendation_count' => $ranking['recommendation_count'],
            'low_confidence' => $ranking['low_confidence'],
            'empty_reason' => $ranking['empty_reason'],
            'max_selected' => (int) config('ai.project_ranking.max_selected_projects', 5),
        ];
    }

    /**
     * @param  list<int>  $orderedProjectIds
     */
    public function save(User $user, JobAnalysis $analysis, array $orderedProjectIds, bool $asManual = true): void
    {
        if ($analysis->user_id !== $user->id) {
            abort(403);
        }

        if (! $analysis->isCompleted()) {
            throw ValidationException::withMessages([
                'project_ids' => 'Project selection is only available for completed analyses.',
            ]);
        }

        $max = (int) config('ai.project_ranking.max_selected_projects', 5);
        $ids = array_values(array_unique(array_map('intval', $orderedProjectIds)));

        if (count($ids) > $max) {
            throw ValidationException::withMessages([
                'project_ids' => "You can select at most {$max} projects.",
            ]);
        }

        $eligible = $this->eligibility->eligibleOwnedByIds($user, $ids, $analysis->profile_id);

        if ($eligible->count() !== count($ids)) {
            throw ValidationException::withMessages([
                'project_ids' => 'One or more projects are invalid, inactive, or not resume-eligible.',
            ]);
        }

        $recommendedIds = collect($this->ranking->rankForAnalysis($user, $analysis)['recommended'])
            ->pluck('id')
            ->all();

        DB::transaction(function () use ($user, $analysis, $ids, $asManual, $recommendedIds) {
            JobAnalysisProjectSelection::query()
                ->where('user_id', $user->id)
                ->where('job_analysis_id', $analysis->id)
                ->delete();

            foreach ($ids as $order => $projectId) {
                $source = $asManual
                    ? JobAnalysisProjectSelection::SOURCE_MANUAL
                    : (
                        in_array($projectId, $recommendedIds, true)
                            ? JobAnalysisProjectSelection::SOURCE_RECOMMENDATION
                            : JobAnalysisProjectSelection::SOURCE_MANUAL
                    );

                JobAnalysisProjectSelection::query()->create([
                    'user_id' => $user->id,
                    'job_analysis_id' => $analysis->id,
                    'project_id' => $projectId,
                    'selected' => true,
                    'display_order' => $order,
                    'selection_source' => $source,
                ]);
            }
        });
    }

    public function resetToRecommendations(User $user, JobAnalysis $analysis): void
    {
        $ranking = $this->ranking->rankForAnalysis($user, $analysis);
        $ids = collect($ranking['recommended'])->pluck('id')->all();

        $this->save($user, $analysis, $ids, asManual: false);
    }
}
