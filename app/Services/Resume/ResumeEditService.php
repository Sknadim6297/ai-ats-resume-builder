<?php

namespace App\Services\Resume;

use App\Models\ResumeDraft;
use App\Models\ResumeVersion;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ResumeEditService
{
    public function __construct(
        private readonly GroundedResumeSchema $schema,
        private readonly ResumeGroundingValidator $grounding,
        private readonly ResumeVersionService $versions,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function save(User $user, ResumeDraft $draft, array $input): ResumeVersion
    {
        if ($draft->user_id !== $user->id) {
            abort(403);
        }

        if (! $draft->isCompleted() || ! is_array($draft->source_snapshot)) {
            throw ValidationException::withMessages([
                'resume' => 'Only completed grounded drafts can be edited.',
            ]);
        }

        $validated = $this->schema->validate($input);
        $grounded = $this->grounding->validate($validated, $draft->source_snapshot);

        return $this->versions->append(
            $user,
            $draft,
            $grounded,
            $draft->source_snapshot,
            ResumeVersion::CHANGE_MANUALLY_EDITED,
        );
    }
}
