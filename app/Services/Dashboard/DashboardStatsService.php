<?php

namespace App\Services\Dashboard;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aggregates dashboard metrics from tables that currently exist.
 * Future tables return zero until their migrations are introduced.
 */
class DashboardStatsService
{
    /**
     * @return array{
     *     resumes_generated: int,
     *     job_analyses: int,
     *     skills: int,
     *     projects: int,
     *     recent_activity: list<array{id:int|string,title:string,status:string,created_at:string}>
     * }
     */
    public function forUser(User $user): array
    {
        $skills = 0;
        if ($user->profile) {
            $skills = $user->profile->skills()->wherePivot('is_active', true)->count();
        }

        return [
            'resumes_generated' => Schema::hasTable('resume_versions')
                ? (int) DB::table('resume_versions')->where('user_id', $user->id)->count()
                : (Schema::hasTable('resume_drafts')
                    ? (int) DB::table('resume_drafts')
                        ->where('user_id', $user->id)
                        ->where('generation_status', 'completed')
                        ->count()
                    : 0),
            'job_analyses' => Schema::hasTable('job_analyses')
                ? (int) DB::table('job_analyses')->where('user_id', $user->id)->count()
                : 0,
            'skills' => $skills,
            'projects' => Schema::hasTable('projects')
                ? Project::query()->where('user_id', $user->id)->where('is_active', true)->count()
                : 0,
            'recent_activity' => $this->recentResumeActivity($user),
        ];
    }

    /**
     * @return list<array{id:int|string,title:string,status:string,created_at:string}>
     */
    private function recentResumeActivity(User $user): array
    {
        if (Schema::hasTable('resume_versions')) {
            return DB::table('resume_versions')
                ->where('user_id', $user->id)
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(['id', 'version_number', 'change_type', 'created_at', 'resume_draft_id'])
                ->map(fn ($row) => [
                    'id' => $row->id,
                    'title' => 'Resume #'.$row->resume_draft_id.' v'.$row->version_number,
                    'status' => (string) ($row->change_type ?? 'unknown'),
                    'created_at' => (string) $row->created_at,
                ])
                ->all();
        }

        if (! Schema::hasTable('resume_drafts')) {
            return [];
        }

        return DB::table('resume_drafts')
            ->where('user_id', $user->id)
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get(['id', 'generation_status', 'updated_at', 'created_at'])
            ->map(fn ($row) => [
                'id' => $row->id,
                'title' => 'Resume draft #'.$row->id,
                'status' => (string) ($row->generation_status ?? 'unknown'),
                'created_at' => (string) ($row->updated_at ?? $row->created_at),
            ])
            ->all();
    }
}
