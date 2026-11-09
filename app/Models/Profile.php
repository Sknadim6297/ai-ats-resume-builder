<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Profile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'full_name',
        'headline',
        'email',
        'phone',
        'location',
        'linkedin_url',
        'github_url',
        'portfolio_url',
        'professional_summary',
        'years_of_experience',
        'additional_links',
        'is_verified',
        'is_default',
        'is_active',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'years_of_experience' => 'integer',
            'additional_links' => 'array',
            'archived_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'profile_skills')
            ->withPivot([
                'id',
                'proficiency',
                'years_experience',
                'last_used_year',
                'is_primary',
                'is_verified',
                'is_active',
                'source',
            ])
            ->withTimestamps();
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function workExperiences(): HasMany
    {
        return $this->hasMany(WorkExperience::class);
    }

    public function educations(): HasMany
    {
        return $this->hasMany(Education::class);
    }

    public function jobAnalyses(): HasMany
    {
        return $this->hasMany(JobAnalysis::class);
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }
}
