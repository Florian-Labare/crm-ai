<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportRow extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_VALID = 'valid';
    public const STATUS_INVALID = 'invalid';
    public const STATUS_DUPLICATE = 'duplicate';
    public const STATUS_IMPORTED = 'imported';

    protected $fillable = [
        'import_session_id',
        'row_number',
        'raw_data',
        'normalized_data',
        'status',
        'matched_client_id',
        'validation_errors',
        'duplicate_matches',
        'duplicate_confidence',
    ];

    protected $casts = [
        'raw_data' => 'array',
        'normalized_data' => 'array',
        'validation_errors' => 'array',
        'duplicate_matches' => 'array',
        'duplicate_confidence' => 'float',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(ImportSession::class, 'import_session_id');
    }

    public function matchedClient(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'matched_client_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isValid(): bool
    {
        return $this->status === self::STATUS_VALID;
    }

    public function isInvalid(): bool
    {
        return $this->status === self::STATUS_INVALID;
    }

    public function isDuplicate(): bool
    {
        return $this->status === self::STATUS_DUPLICATE;
    }

    public function isImported(): bool
    {
        return $this->status === self::STATUS_IMPORTED;
    }

    public function hasPotentialDuplicates(): bool
    {
        return !empty($this->duplicate_matches);
    }
}
