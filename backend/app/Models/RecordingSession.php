<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Recording Session Model
 *
 * Gère les sessions d'enregistrement long (jusqu'à 2h)
 * avec découpage automatique en chunks de 10min max
 */
class RecordingSession extends Model
{
    protected $fillable = [
        'session_id',
        'team_id', // Added team_id
        'user_id',
        'client_id',
        'total_chunks',
        'final_transcription',
        'status',
        'started_at',
        'finalized_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finalized_at' => 'datetime',
    ];

    /**
     * Relation avec l'équipe
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Relation avec l'utilisateur
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relation avec le client (optionnel)
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
