<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientPassif extends Model
{
    protected $table = 'client_passifs';

    protected $fillable = [
        'client_id',
        'nature',
        'preteur',
        'periodicite',
        'montant_remboursement',
        'capital_restant_du',
        'duree_restante',
    ];

    protected $casts = [
        'montant_remboursement' => 'decimal:2',
        'capital_restant_du' => 'decimal:2',
        'duree_restante' => 'integer',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
