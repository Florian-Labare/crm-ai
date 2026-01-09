<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportSession extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ANALYZING = 'analyzing';
    public const STATUS_MAPPING = 'mapping';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'team_id',
        'user_id',
        'import_mapping_id',
        'original_filename',
        'file_path',
        'status',
        'total_rows',
        'processed_rows',
        'success_count',
        'error_count',
        'duplicate_count',
        'detected_columns',
        'ai_suggested_mappings',
        'errors_summary',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'detected_columns' => 'array',
        'ai_suggested_mappings' => 'array',
        'errors_summary' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mapping(): BelongsTo
    {
        return $this->belongsTo(ImportMapping::class, 'import_mapping_id');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class);
    }

    public function getProgressPercentageAttribute(): float
    {
        if ($this->total_rows === 0) {
            return 0;
        }

        return round(($this->processed_rows / $this->total_rows) * 100, 2);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }
}
