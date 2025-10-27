<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AudioRecord extends Model
{
    protected $fillable = [
        'client_id','path','status','transcription','processed_at'
    ];

    protected $casts = [
        'processed_at' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
