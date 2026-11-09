<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkExperienceTechnology extends Model
{
    protected $fillable = [
        'work_experience_id',
        'name',
        'skill_id',
        'sort_order',
    ];

    public function workExperience(): BelongsTo
    {
        return $this->belongsTo(WorkExperience::class);
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }
}
