<?php

namespace App\Services\Resume;

use App\Models\ResumeDraft;
use App\Models\ResumeVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResumeVersionService
{
    public function findOwned(User $user, int $versionId): ResumeVersion
    {
        return ResumeVersion::query()
            ->where('user_id', $user->id)
            ->findOrFail($versionId);
    }

    /**
     * Persist a new immutable version and mark it current.
     * Never overwrites prior version rows.
     *
     * @param  array<string, mixed>  $content
     * @param  array<string, mixed>|null  $snapshot
     */
    public function append(
        User $user,
        ResumeDraft $draft,
        array $content,
        ?array $snapshot,
        string $changeType,
    ): ResumeVersion {
        if ($draft->user_id !== $user->id) {
            abort(403);
        }

        if (! in_array($changeType, ResumeVersion::CHANGE_TYPES, true)) {
            throw ValidationException::withMessages([
                'version' => 'Invalid resume change type.',
            ]);
        }

        return DB::transaction(function () use ($user, $draft, $content, $snapshot, $changeType) {
            // Lock draft row to prevent concurrent version-number collisions.
            $locked = ResumeDraft::query()->whereKey($draft->id)->lockForUpdate()->firstOrFail();

            $next = ((int) ResumeVersion::query()
                ->where('resume_draft_id', $locked->id)
                ->max('version_number')) + 1;

            ResumeVersion::query()
                ->where('resume_draft_id', $locked->id)
                ->where('is_current', true)
                ->update(['is_current' => false]);

            $version = ResumeVersion::query()->create([
                'user_id' => $user->id,
                'resume_draft_id' => $locked->id,
                'job_analysis_id' => $locked->job_analysis_id,
                'version_number' => $next,
                'change_type' => $changeType,
                'template_version' => $locked->template_version ?: ResumeDraft::TEMPLATE_VERSION,
                'resume_schema_version' => $locked->resume_schema_version ?: ResumeDraft::SCHEMA_VERSION,
                'structured_content' => $content,
                'source_snapshot' => $snapshot,
                'is_current' => true,
            ]);

            $locked->update([
                'structured_content' => $content,
                'source_snapshot' => $snapshot,
                'current_version_number' => $version->version_number,
                'current_version_id' => $version->id,
                'generation_status' => ResumeDraft::STATUS_COMPLETED,
                'safe_error' => null,
            ]);

            return $version;
        });
    }

    public function restore(User $user, ResumeVersion $version): ResumeVersion
    {
        $owned = $this->findOwned($user, $version->id);
        $draft = ResumeDraft::query()
            ->where('user_id', $user->id)
            ->findOrFail($owned->resume_draft_id);

        return $this->append(
            $user,
            $draft,
            $owned->structured_content ?? [],
            $owned->source_snapshot,
            ResumeVersion::CHANGE_RESTORED,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForDraft(User $user, ResumeDraft $draft): array
    {
        if ($draft->user_id !== $user->id) {
            abort(403);
        }

        return ResumeVersion::query()
            ->where('user_id', $user->id)
            ->where('resume_draft_id', $draft->id)
            ->orderByDesc('version_number')
            ->get()
            ->map(fn (ResumeVersion $version) => [
                'id' => $version->id,
                'version_number' => $version->version_number,
                'change_type' => $version->change_type,
                'is_current' => $version->is_current,
                'created_at' => optional($version->created_at)?->toDateTimeString(),
            ])
            ->all();
    }
}
