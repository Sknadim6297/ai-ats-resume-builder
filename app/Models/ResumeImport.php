<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResumeImport extends Model
{
    public const STATUS_UPLOADED = 'uploaded';

    public const STATUS_EXTRACTING = 'extracting';

    public const STATUS_EXTRACTED = 'extracted';

    public const STATUS_PARSING = 'parsing';

    public const STATUS_PARSED = 'parsed';

    public const STATUS_REVIEW = 'review';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STRATEGY_NEW_PROFILE = 'new_profile';

    public const STRATEGY_MERGE = 'merge';

    public const STRATEGY_REPLACE_FIELDS = 'replace_fields';

    /** @deprecated Use STRATEGY_NEW_PROFILE */
    public const MERGE_NEW_PROFILE = self::STRATEGY_NEW_PROFILE;

    /** @deprecated Use STRATEGY_MERGE */
    public const MERGE_MERGE = self::STRATEGY_MERGE;

    protected $fillable = [
        'user_id',
        'profile_id',
        'original_filename',
        'mime_type',
        'file_size',
        'disk',
        'storage_path',
        'status',
        'extraction_method',
        'provider',
        'model',
        'extracted_text',
        'parsed_payload',
        'review_payload',
        'merge_strategy',
        'safe_error',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'parsed_payload' => 'array',
            'review_payload' => 'array',
            'confirmed_at' => 'datetime',
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

    public function isOwnedBy(User $user): bool
    {
        return (int) $this->user_id === (int) $user->id;
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
        ], true);
    }
}
