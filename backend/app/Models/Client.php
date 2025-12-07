<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Client extends Model
{
    protected $fillable = [
        'team_id', // Added team_id
        'user_id',
        'der_charge_clientele_id',
        'der_lieu_rdv',
        'der_date_rdv',
        'der_heure_rdv',
        'civilite',
        'nom',
        'nom_jeune_fille',
        'prenom',
        'date_naissance',
        'lieu_naissance',
        'nationalite',
        'situation_matrimoniale',
        'date_situation_matrimoniale',
        'situation_actuelle',
        'profession',
        'date_evenement_professionnel',
        'risques_professionnels',
        'details_risques_professionnels',
        'revenus_annuels',
        'adresse',
        'code_postal',
        'ville',
        'residence_fiscale',
        'telephone',
        'email',
        'fumeur',
        'activites_sportives',
        'details_activites_sportives',
        'niveau_activites_sportives',
        // 'nombre_enfants', // SUPPRIMÉ : Colonne inexistante en base
        'besoins',
        'transcription_path',
        'consentement_audio',
        'charge_clientele',
        'chef_entreprise',
        'statut',
        'travailleur_independant',
        'mandataire_social',
    ];

    protected $casts = [
        // 'nombre_enfants' => 'integer', // SUPPRIMÉ
        'besoins' => 'array',
        'consentement_audio' => 'boolean',
        'risques_professionnels' => 'boolean',
        'fumeur' => 'boolean',
        'activites_sportives' => 'boolean',
        'chef_entreprise' => 'boolean',
        'travailleur_independant' => 'boolean',
        'mandataire_social' => 'boolean',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new \App\Scopes\TeamScope);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conjoint(): HasOne
    {
        return $this->hasOne(Conjoint::class);
    }

    public function enfants(): HasMany
    {
        return $this->hasMany(Enfant::class);
    }

    public function chargeClientele(): BelongsTo
    {
        return $this->belongsTo(User::class, 'der_charge_clientele_id');
    }

    public function santeSouhait(): HasOne
    {
        return $this->hasOne(SanteSouhait::class);
    }

    public function baePrevoyance(): HasOne
    {
        return $this->hasOne(BaePrevoyance::class);
    }

    public function baeRetraite(): HasOne
    {
        return $this->hasOne(BaeRetraite::class);
    }

    public function baeEpargne(): HasOne
    {
        return $this->hasOne(BaeEpargne::class);
    }

    public function questionnaireRisque(): HasOne
    {
        return $this->hasOne(QuestionnaireRisque::class);
    }

    public function generatedDocuments(): HasMany
    {
        return $this->hasMany(GeneratedDocument::class);
    }

    public function revenus(): HasMany
    {
        return $this->hasMany(ClientRevenu::class);
    }

    public function passifs(): HasMany
    {
        return $this->hasMany(ClientPassif::class);
    }

    public function actifsFinanciers(): HasMany
    {
        return $this->hasMany(ClientActifFinancier::class);
    }

    public function biensImmobiliers(): HasMany
    {
        return $this->hasMany(ClientBienImmobilier::class);
    }

    public function autresEpargnes(): HasMany
    {
        return $this->hasMany(ClientAutreEpargne::class);
    }
}
