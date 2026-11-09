<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class JobAnalysis extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (JobAnalysis $analysis): void {
            if (! $analysis->profile_id && $analysis->user_id) {
                $analysis->profile_id = Profile::query()
                    ->where('user_id', $analysis->user_id)
                    ->whereNull('archived_at')
                    ->orderByDesc('is_default')
                    ->value('id');
            }
        });
    }

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'profile_id',
        'job_title',
        'company_name',
        'job_location',
        'job_url',
        'job_description',
        'analysis_json',
        'status',
        'provider',
        'model',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'analysis_json' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function skillDecisions(): HasMany
    {
        return $this->hasMany(JobAnalysisSkillDecision::class);
    }

    public function projectSelections(): HasMany
    {
        return $this->hasMany(JobAnalysisProjectSelection::class);
    }

    public function resumeDraft(): HasOne
    {
        return $this->hasOne(ResumeDraft::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }
}
