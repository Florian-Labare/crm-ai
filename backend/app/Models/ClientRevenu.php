<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientRevenu extends Model
{
    protected $table = 'client_revenus';

    protected $fillable = [
        'client_id',
        'nature',
        'details',
        'periodicite',
        'montant',
    ];

    protected $casts = [
        'montant' => 'decimal:2',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
