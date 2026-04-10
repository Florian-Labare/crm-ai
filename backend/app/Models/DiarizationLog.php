<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiarizationLog extends Model
{
    protected $fillable = [
        'audio_record_id',
        'recording_session_id',
        'team_id',
        'user_id',
        'status',
        'error_message',
        'error_code',
        'duration_ms',
        'audio_duration_seconds',
        'file_size_bytes',
        'speakers_detected',
        'broker_speaker_id',
        'client_speakers',
        'broker_duration_seconds',
        'client_duration_seconds',
        'broker_segments_count',
        'client_segments_count',
        'single_speaker_mode',
        'model_version',
        'used_gpu',
        'raw_output',
    ];

    protected $casts = [
        'client_speakers' => 'array',
        'raw_output' => 'array',
        'single_speaker_mode' => 'boolean',
        'used_gpu' => 'boolean',
        'broker_duration_seconds' => 'float',
        'client_duration_seconds' => 'float',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new \App\Scopes\TeamScope);
    }

    public function audioRecord(): BelongsTo
    {
        return $this->belongsTo(AudioRecord::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scopes pour le monitoring
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    public function scopeFailed($query)
    {
        return $query->whereIn('status', ['failed', 'timeout']);
    }

    public function scopeFallback($query)
    {
        return $query->where('status', 'fallback');
    }

    public function scopeInPeriod($query, int $days)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
