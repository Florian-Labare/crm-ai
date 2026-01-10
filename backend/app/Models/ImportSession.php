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
        'database_connection_id',
        'source_table',
        'source_query',
        'original_filename',
        'file_path',
        'status',
        'rgpd_consent_given',
        'legal_basis',
        'legal_basis_details',
        'consent_timestamp',
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
        'retention_until',
    ];

    protected $casts = [
        'detected_columns' => 'array',
        'ai_suggested_mappings' => 'array',
        'errors_summary' => 'array',
        'rgpd_consent_given' => 'boolean',
        'consent_timestamp' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'retention_until' => 'datetime',
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

    public function databaseConnection(): BelongsTo
    {
        return $this->belongsTo(DatabaseConnection::class);
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

    public function isDatabaseImport(): bool
    {
        return $this->database_connection_id !== null;
    }

    public function isFileImport(): bool
    {
        return $this->file_path !== null && $this->database_connection_id === null;
    }

    public function hasRgpdConsent(): bool
    {
        return $this->rgpd_consent_given && $this->legal_basis !== null;
    }

    public function isExpired(): bool
    {
        return $this->retention_until !== null && $this->retention_until->isPast();
    }

    public function importedClients(): HasMany
    {
        return $this->hasMany(Client::class, 'import_session_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(ImportAuditLog::class);
    }
}
