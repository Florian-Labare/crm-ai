<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientPendingChange extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'user_id',
        'team_id',
        'audio_record_id',
        'extracted_data',
        'relational_data', // Passifs, actifs, BAE, conjoint, enfants
        'changes_diff',
        'status',
        'user_decisions',
        'source',
        'notes',
        'reviewed_at',
        'applied_at',
        'reviewed_by',
    ];

    protected $casts = [
        'extracted_data' => 'array',
        'relational_data' => 'array',
        'changes_diff' => 'array',
        'user_decisions' => 'array',
        'reviewed_at' => 'datetime',
        'applied_at' => 'datetime',
    ];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_REVIEWING = 'reviewing';
    const STATUS_APPLIED = 'applied';
    const STATUS_PARTIALLY_APPLIED = 'partially_applied';
    const STATUS_REJECTED = 'rejected';

    // Source constants
    const SOURCE_AUDIO = 'audio';
    const SOURCE_MANUAL = 'manual';
    const SOURCE_IMPORT = 'import';

    // ============================================
    // RELATIONS
    // ============================================

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function audioRecord(): BelongsTo
    {
        return $this->belongsTo(AudioRecord::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    // ============================================
    // SCOPES
    // ============================================

    /**
     * Scope pour filtrer par utilisateur
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope pour filtrer par client
     */
    public function scopeForClient($query, int $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    /**
     * Scope pour filtrer par statut pending
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    // ============================================
    // ACCESSORS
    // ============================================

    /**
     * Nombre total de changements
     */
    public function getChangesCountAttribute(): int
    {
        $diff = $this->changes_diff ?? [];
        return collect($diff)->filter(fn($change) => $change['has_change'] ?? false)->count();
    }

    /**
     * Nombre de conflits
     */
    public function getConflictsCountAttribute(): int
    {
        $diff = $this->changes_diff ?? [];
        return collect($diff)->filter(fn($change) => $change['is_conflict'] ?? false)->count();
    }

    /**
     * Nombre de champs critiques
     */
    public function getCriticalCountAttribute(): int
    {
        $diff = $this->changes_diff ?? [];
        return collect($diff)->filter(fn($change) => $change['is_critical'] ?? false)->count();
    }
}
