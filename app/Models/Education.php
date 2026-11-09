<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Education extends Model
{
    use HasFactory;

    protected $table = 'educations';

    protected static function booted(): void
    {
        static::creating(function (Education $education): void {
            if (! $education->profile_id && $education->user_id) {
                $education->profile_id = Profile::query()
                    ->where('user_id', $education->user_id)
                    ->whereNull('archived_at')
                    ->orderByDesc('is_default')
                    ->value('id');
            }
        });
    }

    protected $fillable = [
        'user_id',
        'profile_id',
        'institution_name',
        'degree',
        'field_of_study',
        'start_year',
        'end_year',
        'grade',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'start_year' => 'integer',
            'end_year' => 'integer',
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
}
