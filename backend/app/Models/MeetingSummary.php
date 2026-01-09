<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingSummary extends Model
{
    protected $fillable = [
        'client_id',
        'audio_record_id',
        'created_by',
        'summary_text',
        'summary_json',
    ];

    protected $casts = [
        'summary_json' => 'array',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function audioRecord(): BelongsTo
    {
        return $this->belongsTo(AudioRecord::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
