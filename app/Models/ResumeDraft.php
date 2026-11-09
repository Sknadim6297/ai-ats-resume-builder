<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResumeDraft extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const TEMPLATE_VERSION = 'ats-universal-v1';

    public const SCHEMA_VERSION = '1.0';

    protected $fillable = [
        'user_id',
        'job_analysis_id',
        'template_version',
        'resume_schema_version',
        'structured_content',
        'source_snapshot',
        'provider',
        'model',
        'generation_status',
        'safe_error',
        'current_version_number',
        'current_version_id',
    ];

    protected function casts(): array
    {
        return [
            'structured_content' => 'array',
            'source_snapshot' => 'array',
            'current_version_number' => 'integer',
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

    public function versions(): HasMany
    {
        return $this->hasMany(ResumeVersion::class);
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(ResumeVersion::class, 'current_version_id');
    }

    public function isCompleted(): bool
    {
        return $this->generation_status === self::STATUS_COMPLETED
            && is_array($this->structured_content);
    }
}
