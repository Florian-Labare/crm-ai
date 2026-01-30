<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Conjoint extends Model
{
    protected $fillable = [
        'client_id',
        // Identité
        'nom',
        'nom_jeune_fille',
        'prenom',
        'date_naissance',
        'lieu_naissance',
        'nationalite',
        // Coordonnées
        'telephone',
        'email',
        'adresse',
        'code_postal',
        'ville',
        // Professionnel
        'profession',
        'situation_professionnelle',
        'situation_chomage',
        'statut',
        'chef_entreprise',
        'travailleur_independant',
        'mandataire_social',
        'situation_actuelle_statut',
        'date_evenement_professionnel',
        'risques_professionnels',
        'details_risques_professionnels',
        'revenus_annuels',
        // Mode de vie
        'fumeur',
        'activites_sportives',
        'niveau_activite_sportive',
        'details_activites_sportives',
        'km_parcourus_annuels',
    ];

    protected $casts = [
        'date_naissance' => 'date',
        'date_evenement_professionnel' => 'date',
        'risques_professionnels' => 'boolean',
        'chef_entreprise' => 'boolean',
        'travailleur_independant' => 'boolean',
        'mandataire_social' => 'boolean',
        'fumeur' => 'boolean',
        'activites_sportives' => 'boolean',
        'revenus_annuels' => 'decimal:2',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
