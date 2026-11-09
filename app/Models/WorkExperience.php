<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkExperience extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (WorkExperience $experience): void {
            if (! $experience->profile_id && $experience->user_id) {
                $experience->profile_id = Profile::query()
                    ->where('user_id', $experience->user_id)
                    ->whereNull('archived_at')
                    ->orderByDesc('is_default')
                    ->value('id');
            }
        });
    }

    protected $fillable = [
        'user_id',
        'profile_id',
        'company_name',
        'job_title',
        'employment_type',
        'location',
        'start_date',
        'end_date',
        'is_current',
        'responsibilities',
        'achievements',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_current' => 'boolean',
            'responsibilities' => 'array',
            'achievements' => 'array',
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
        return $this->hasMany(WorkExperienceTechnology::class)->orderBy('sort_order');
    }
}
