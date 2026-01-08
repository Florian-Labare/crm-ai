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
        'situation_professionnelle',
        'situation_chomage',
        'statut',
        'chef_entreprise',
        'travailleur_independant',
        'situation_actuelle_statut',
        'niveau_activite_sportive',
        'details_activites_sportives',
        'date_evenement_professionnel',
        'risques_professionnels',
        'details_risques_professionnels',
        'telephone',
        'adresse',
        'code_postal',
        'ville',
        'fumeur',
        'km_parcourus_annuels',
    ];

    protected $casts = [
        'risques_professionnels' => 'boolean',
        'chef_entreprise' => 'boolean',
        'travailleur_independant' => 'boolean',
        'fumeur' => 'boolean',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
