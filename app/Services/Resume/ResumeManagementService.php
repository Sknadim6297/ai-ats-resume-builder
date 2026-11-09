<?php

namespace App\Services\Resume;

use App\Models\ResumeDraft;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ResumeManagementService
{
    /**
     * @param  array{search?:string,sort?:string}  $filters
     */
    public function listForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = ResumeDraft::query()
            ->with(['jobAnalysis', 'currentVersion'])
            ->where('user_id', $user->id)
            ->where('generation_status', ResumeDraft::STATUS_COMPLETED);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($builder) use ($search) {
                $builder->whereHas('jobAnalysis', function ($q) use ($search) {
                    $q->where('job_title', 'like', '%'.$search.'%')
                        ->orWhere('company_name', 'like', '%'.$search.'%');
                })->orWhere('id', $search);
            });
        }

        $sort = $filters['sort'] ?? 'updated_desc';
        match ($sort) {
            'updated_asc' => $query->orderBy('updated_at'),
            'title_asc' => $query->orderBy(
                \App\Models\JobAnalysis::query()
                    ->select('job_title')
                    ->whereColumn('job_analyses.id', 'resume_drafts.job_analysis_id')
                    ->limit(1)
            ),
            default => $query->orderByDesc('updated_at'),
        };

        return $query->paginate(10)->withQueryString();
    }

    /**
     * @return array<string, mixed>
     */
    public function presentListItem(ResumeDraft $draft): array
    {
        $analysis = $draft->jobAnalysis;
        $version = $draft->currentVersion;

        return [
            'id' => $draft->id,
            'job_analysis_id' => $draft->job_analysis_id,
            'job_title' => $analysis?->job_title
                ?? data_get($analysis?->analysis_json, 'job_title')
                ?? 'Untitled role',
            'company_name' => $analysis?->company_name,
            'status' => $draft->generation_status,
            'version_number' => $draft->current_version_number ?? $version?->version_number,
            'change_type' => $version?->change_type,
            'current_version_id' => $draft->current_version_id ?? $version?->id,
            'updated_at' => optional($draft->updated_at)?->toDateTimeString(),
        ];
    }
}
