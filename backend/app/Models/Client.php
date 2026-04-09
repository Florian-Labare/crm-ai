<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Client extends Model
{
    protected $fillable = [
        'team_id',
        'is_client',
        'is_archived',
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
        'km_parcourus_annuels',
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
        'besoins' => 'array',
        'consentement_audio' => 'boolean',
        'risques_professionnels' => 'boolean',
        'fumeur' => 'boolean',
        'activites_sportives' => 'boolean',
        'chef_entreprise' => 'boolean',
        'travailleur_independant' => 'boolean',
        'mandataire_social' => 'boolean',
        'is_client' => 'boolean',
        'is_archived' => 'boolean',
    ];

    public function setEmailAttribute(?string $value): void
    {
        $this->attributes['email'] = $value ? strtolower(trim($value)) : null;
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new \App\Scopes\TeamScope);
    }

    // ===== SCOPES =====

    /**
     * Scope pour les prospects (non-clients, non-archivés)
     */
    public function scopeProspects($query)
    {
        return $query->where('is_client', false)->where('is_archived', false);
    }

    /**
     * Scope pour les clients (is_client = true, non-archivés)
     */
    public function scopeClients($query)
    {
        return $query->where('is_client', true)->where('is_archived', false);
    }

    /**
     * Scope pour les archivés (RAF)
     */
    public function scopeArchived($query)
    {
        return $query->where('is_archived', true);
    }

    /**
     * Scope pour les actifs (non-archivés)
     */
    public function scopeActive($query)
    {
        return $query->where('is_archived', false);
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

    public function complianceDocuments(): HasMany
    {
        return $this->hasMany(ClientComplianceDocument::class);
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

    public function charges(): HasMany
    {
        return $this->hasMany(ClientCharge::class);
    }

    public function audioRecords(): HasMany
    {
        return $this->hasMany(AudioRecord::class);
    }

    public function recordingSessions(): HasMany
    {
        return $this->hasMany(RecordingSession::class);
    }

    public function meetingSummaries(): HasMany
    {
        return $this->hasMany(MeetingSummary::class);
    }

    public function contrats(): HasMany
    {
        return $this->hasMany(ClientContrat::class);
    }
}
