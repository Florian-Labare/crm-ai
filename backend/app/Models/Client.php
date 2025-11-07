<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    protected $fillable = [
        // Identité de base
        'civilite', 'nom', 'nom_jeune_fille', 'prenom', 'datedenaissance', 'lieudenaissance', 'nationalite',
        // Situation
        'situationmatrimoniale', 'date_situation_matrimoniale', 'situation_actuelle',
        // Professionnel
        'profession', 'date_evenement_professionnel', 'risques_professionnels', 'details_risques_professionnels',
        'revenusannuels',
        // Coordonnées
        'adresse', 'code_postal', 'ville', 'residence_fiscale', 'telephone', 'email',
        // Mode de vie
        'fumeur', 'activites_sportives', 'details_activites_sportives', 'niveau_activites_sportives',
        // Autres
        'nombreenfants', 'besoins', 'transcription_path', 'consentement_audio', 'charge_clientele'
    ];

    protected $casts = [
        'datedenaissance'                 => 'date',
        'date_situation_matrimoniale'     => 'date',
        'date_evenement_professionnel'    => 'date',
        'revenusannuels'                  => 'decimal:2',
        'nombreenfants'                   => 'integer',
        'besoins'                         => 'array',
        'consentement_audio'              => 'boolean',
        'risques_professionnels'          => 'boolean',
        'fumeur'                          => 'boolean',
        'activites_sportives'             => 'boolean',
    ];

    // Relations
    public function conjoint(): HasOne
    {
        return $this->hasOne(Conjoint::class);
    }

    public function enfants(): HasMany
    {
        return $this->hasMany(Enfant::class);
    }

    public function entreprise(): HasOne
    {
        return $this->hasOne(Entreprise::class);
    }

    public function santeSouhait(): HasOne
    {
        return $this->hasOne(SanteSouhait::class);
    }
}
