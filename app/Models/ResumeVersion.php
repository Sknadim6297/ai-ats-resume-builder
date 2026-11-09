<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResumeVersion extends Model
{
    use HasFactory;

    public const CHANGE_AI_GENERATED = 'ai_generated';

    public const CHANGE_MANUALLY_EDITED = 'manually_edited';

    public const CHANGE_RESTORED = 'restored';

    public const CHANGE_TYPES = [
        self::CHANGE_AI_GENERATED,
        self::CHANGE_MANUALLY_EDITED,
        self::CHANGE_RESTORED,
    ];

    protected $fillable = [
        'user_id',
        'resume_draft_id',
        'job_analysis_id',
        'version_number',
        'change_type',
        'template_version',
        'resume_schema_version',
        'structured_content',
        'source_snapshot',
        'is_current',
    ];

    protected function casts(): array
    {
        return [
            'structured_content' => 'array',
            'source_snapshot' => 'array',
            'is_current' => 'boolean',
            'version_number' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function draft(): BelongsTo
    {
        return $this->belongsTo(ResumeDraft::class, 'resume_draft_id');
    }

    public function jobAnalysis(): BelongsTo
    {
        return $this->belongsTo(JobAnalysis::class);
    }
}
