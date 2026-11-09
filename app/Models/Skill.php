<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Skill extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'category',
    ];

    public function profiles(): BelongsToMany
    {
        return $this->belongsToMany(Profile::class, 'profile_skills')
            ->withPivot([
                'id',
                'proficiency',
                'years_experience',
                'last_used_year',
                'is_primary',
                'is_verified',
                'is_active',
            ])
            ->withTimestamps();
    }

    public static function normalizeName(string $name): string
    {
        return trim(preg_replace('/\s+/', ' ', $name) ?? $name);
    }

    public static function makeSlug(string $name): string
    {
        return Str::slug(self::normalizeName($name));
    }
}
