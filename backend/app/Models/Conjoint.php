<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Conjoint extends Model
{
    protected $fillable = [
        'client_id',
        'nom',
        'nom_jeune_fille',
        'prenom',
        'date_naissance',
        'lieu_naissance',
        'nationalite',
        'profession',
        'chef_entreprise',
        'situation_actuelle_statut',
        'date_evenement_professionnel',
        'risques_professionnels',
        'details_risques_professionnels',
        'telephone',
        'adresse',
    ];

    protected $casts = [
        'risques_professionnels' => 'boolean',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
