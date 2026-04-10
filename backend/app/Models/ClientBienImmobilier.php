<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientBienImmobilier extends Model
{
    protected $table = 'client_biens_immobiliers';

    protected $fillable = [
        'client_id',
        'designation',
        'detenteur',
        'forme_propriete',
        'valeur_actuelle_estimee',
        'annee_acquisition',
        'valeur_acquisition',
    ];

    protected $casts = [
        'valeur_actuelle_estimee' => 'decimal:2',
        'annee_acquisition' => 'integer',
        'valeur_acquisition' => 'decimal:2',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
