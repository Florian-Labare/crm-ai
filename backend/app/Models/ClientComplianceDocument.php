<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientComplianceDocument extends Model
{
    protected $fillable = [
        'client_id',
        'uploaded_by',
        'document_type',
        'category',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
        'status',
        'validated_at',
        'validated_by',
        'expires_at',
        'document_date',
        'notes',
        'rejection_reason',
    ];

    protected $casts = [
        'validated_at' => 'datetime',
        'expires_at' => 'date',
        'document_date' => 'date',
        'file_size' => 'integer',
    ];

    /**
     * Labels des types de documents
     */
    public const DOCUMENT_LABELS = [
        // Documents d'identité
        'cni' => "Carte d'identité",
        'passeport' => 'Passeport',
        'titre_sejour' => 'Titre de séjour',

        // Documents fiscaux
        'avis_imposition' => "Avis d'imposition",
        'avis_imposition_n1' => "Avis d'imposition N-1",
        'avis_imposition_n2' => "Avis d'imposition N-2",

        // Documents réglementaires par besoin
        'lettre_mission_prevoyance' => 'Lettre de mission - Prévoyance',
        'der_prevoyance' => 'DER - Prévoyance',
        'fiche_conseil_prevoyance' => 'Fiche conseil - Prévoyance',

        'lettre_mission_retraite' => 'Lettre de mission - Retraite',
        'der_retraite' => 'DER - Retraite',
        'fiche_conseil_retraite' => 'Fiche conseil - Retraite',

        'lettre_mission_epargne' => 'Lettre de mission - Épargne',
        'der_epargne' => 'DER - Épargne',
        'fiche_conseil_epargne' => 'Fiche conseil - Épargne',

        'lettre_mission_sante' => 'Lettre de mission - Santé',
        'fiche_ipid_sante' => 'Fiche IPID - Santé',
        'devis_sante' => 'Devis - Santé',

        'lettre_mission_immobilier' => 'Lettre de mission - Immobilier',
        'der_immobilier' => 'DER - Immobilier',

        'lettre_mission_fiscalite' => 'Lettre de mission - Fiscalité',
        'der_fiscalite' => 'DER - Fiscalité',

        // Documents généraux
        'mandat_recherche' => 'Mandat de recherche',
        'rgpd_consentement' => 'Consentement RGPD',
        'autre' => 'Autre document',
    ];

    /**
     * Catégories de documents
     */
    public const CATEGORIES = [
        'identity' => 'Identité',
        'fiscal' => 'Fiscal',
        'regulatory' => 'Réglementaire',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    /**
     * Vérifie si le document est expiré
     */
    public function isExpired(): bool
    {
        if (!$this->expires_at) {
            return false;
        }
        return $this->expires_at->isPast();
    }

    /**
     * Vérifie si le document est valide (validé et non expiré)
     */
    public function isValid(): bool
    {
        return $this->status === 'validated' && !$this->isExpired();
    }

    /**
     * Retourne le label du type de document
     */
    public function getDocumentLabelAttribute(): string
    {
        return self::DOCUMENT_LABELS[$this->document_type] ?? $this->document_type;
    }

    /**
     * Retourne le label de la catégorie
     */
    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }
}
