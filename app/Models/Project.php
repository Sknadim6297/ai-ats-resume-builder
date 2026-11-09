<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Project $project): void {
            if (! $project->profile_id && $project->user_id) {
                $project->profile_id = Profile::query()
                    ->where('user_id', $project->user_id)
                    ->whereNull('archived_at')
                    ->orderByDesc('is_default')
                    ->value('id');
            }
        });
    }

    protected $fillable = [
        'user_id',
        'profile_id',
        'name',
        'short_description',
        'detailed_description',
        'project_type',
        'github_url',
        'live_url',
        'start_date',
        'end_date',
        'is_ongoing',
        'is_active',
        'include_in_resume',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_ongoing' => 'boolean',
            'is_active' => 'boolean',
            'include_in_resume' => 'boolean',
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

    public function technologies(): HasMany
    {
        return $this->hasMany(ProjectTechnology::class)->orderBy('sort_order');
    }

    public function features(): HasMany
    {
        return $this->hasMany(ProjectFeature::class)->orderBy('sort_order');
    }

    public function responsibilities(): HasMany
    {
        return $this->hasMany(ProjectResponsibility::class)->orderBy('sort_order');
    }
}
