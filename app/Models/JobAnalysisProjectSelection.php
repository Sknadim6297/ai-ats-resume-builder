<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobAnalysisProjectSelection extends Model
{
    use HasFactory;

    public const SOURCE_RECOMMENDATION = 'recommendation';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCES = [
        self::SOURCE_RECOMMENDATION,
        self::SOURCE_MANUAL,
    ];

    protected $fillable = [
        'user_id',
        'job_analysis_id',
        'project_id',
        'selected',
        'display_order',
        'selection_source',
    ];

    protected function casts(): array
    {
        return [
            'selected' => 'boolean',
            'display_order' => 'integer',
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

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
