<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AudioRecord extends Model
{
    protected $fillable = [
        'team_id',
        'user_id',
        'client_id',
        'path',
        'status',
        'transcription',
        'processed_at',
        // Champs de diarisation
        'diarization_data',
        'speaker_corrections',
        'diarization_success',
        'speakers_corrected',
        'corrected_at',
        'corrected_by',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
        'diarization_data' => 'array',
        'speaker_corrections' => 'array',
        'diarization_success' => 'boolean',
        'speakers_corrected' => 'boolean',
        'corrected_at' => 'datetime',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new \App\Scopes\TeamScope);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function corrector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }

    /**
     * Applique une correction de speaker
     */
    public function applySpeakerCorrection(string $originalSpeaker, string $correctedRole, int $userId): self
    {
        $corrections = $this->speaker_corrections ?? [];
        $corrections[$originalSpeaker] = [
            'role' => $correctedRole, // 'broker' ou 'client'
            'corrected_at' => now()->toISOString(),
            'corrected_by' => $userId
        ];

        $this->speaker_corrections = $corrections;
        $this->speakers_corrected = true;
        $this->corrected_at = now();
        $this->corrected_by = $userId;
        $this->save();

        return $this;
    }

    /**
     * Récupère les speakers avec leurs rôles corrigés
     */
    public function getSpeakersWithRoles(): array
    {
        $diarizationData = $this->diarization_data;
        if (!$diarizationData) {
            return [];
        }

        $corrections = $this->speaker_corrections ?? [];
        $speakers = [];

        // Broker original
        if (!empty($diarizationData['courtier_speaker'])) {
            $brokerId = $diarizationData['courtier_speaker'];
            $speakers[$brokerId] = [
                'id' => $brokerId,
                'original_role' => 'broker',
                'current_role' => $corrections[$brokerId]['role'] ?? 'broker',
                'duration' => $diarizationData['stats']['courtier_duration'] ?? 0,
                'segments_count' => $diarizationData['stats']['courtier_num_segments'] ?? 0,
                'corrected' => isset($corrections[$brokerId])
            ];
        }

        // Clients originaux
        foreach ($diarizationData['client_speakers'] ?? [] as $clientId) {
            $speakers[$clientId] = [
                'id' => $clientId,
                'original_role' => 'client',
                'current_role' => $corrections[$clientId]['role'] ?? 'client',
                'corrected' => isset($corrections[$clientId])
            ];
        }

        return $speakers;
    }

    /**
     * Vérifie si la diarisation a détecté plusieurs locuteurs
     */
    public function hasMultipleSpeakers(): bool
    {
        return ($this->diarization_data['total_speakers'] ?? 0) > 1;
    }

    /**
     * Scope pour les enregistrements avec diarisation réussie
     */
    public function scopeWithDiarization($query)
    {
        return $query->where('diarization_success', true);
    }

    /**
     * Scope pour les enregistrements nécessitant une correction
     */
    public function scopeNeedsReview($query)
    {
        return $query->where('diarization_success', true)
            ->where('speakers_corrected', false);
    }
}
