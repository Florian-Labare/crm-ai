<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientCharge extends Model
{
    protected $table = 'client_charges';

    protected $fillable = [
        'client_id',
        'nature',
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
