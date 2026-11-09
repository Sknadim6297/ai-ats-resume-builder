<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobAnalysisSkillDecision extends Model
{
    use HasFactory;

    public const REQUIREMENT_REQUIRED = 'required';

    public const REQUIREMENT_PREFERRED = 'preferred';

    public const DECISION_PENDING = 'pending';

    public const DECISION_ADD_TO_PROFILE = 'add_to_profile';

    public const DECISION_RESUME_ONLY = 'resume_only';

    public const DECISION_IGNORE = 'ignore';

    public const CONFIRMATION_NOT_CONFIRMED = 'not_confirmed';

    public const CONFIRMATION_USER_CONFIRMED = 'user_confirmed';

    public const CONFIRMATION_NOT_REQUIRED = 'not_required';

    public const DECISIONS = [
        self::DECISION_PENDING,
        self::DECISION_ADD_TO_PROFILE,
        self::DECISION_RESUME_ONLY,
        self::DECISION_IGNORE,
    ];

    public const REQUIREMENT_TYPES = [
        self::REQUIREMENT_REQUIRED,
        self::REQUIREMENT_PREFERRED,
    ];

    protected $fillable = [
        'user_id',
        'job_analysis_id',
        'canonical_skill_name',
        'normalized_skill_key',
        'requirement_type',
        'decision',
        'confirmation_status',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function jobAnalysis(): BelongsTo
    {
        return $this->belongsTo(JobAnalysis::class);
    }

    public function requiresUserConfirmation(): bool
    {
        return in_array($this->decision, [
            self::DECISION_ADD_TO_PROFILE,
            self::DECISION_RESUME_ONLY,
        ], true);
    }
}
