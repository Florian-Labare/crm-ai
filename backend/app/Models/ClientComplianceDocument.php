<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ClientComplianceDocument extends Model
{
    protected $fillable = [
        'client_id',
        'uploaded_by',
        'document_type',
        'category',
        'tags',
        'custom_label',
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
        'tags' => 'array',
    ];

    /**
     * Tags disponibles pour les documents signés
     */
    public const AVAILABLE_TAGS = [
        'prevoyance' => 'Prévoyance',
        'retraite' => 'Retraite',
        'epargne' => 'Épargne',
        'sante' => 'Santé',
        'immobilier' => 'Immobilier',
        'fiscalite' => 'Fiscalité',
    ];

    /**
     * Labels des types de documents
     */
    public const DOCUMENT_LABELS = [
        // Documents d'identité
        'cni' => "Carte d'identité",
        'passeport' => 'Passeport',
        'titre_sejour' => 'Titre de séjour',

        // Documents bancaires
        'rib' => 'RIB',

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
        'signed_document' => 'Document signé',
    ];

    /**
     * Catégories de documents
     */
    public const CATEGORIES = [
        'identity' => 'Identité',
        'banking' => 'Bancaire',
        'fiscal' => 'Fiscal',
        'regulatory' => 'Réglementaire',
        'signed' => 'Documents signés',
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
     * Scope pour les documents expirant bientôt
     */
    public function scopeExpiringSoon($query, int $days = 90)
    {
        return $query->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays($days))
            ->where('expires_at', '>', now());
    }

    /**
     * Scope pour les documents expirés
     */
    public function scopeExpired($query)
    {
        return $query->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());
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
     * Vérifie si le document expire bientôt
     */
    public function isExpiringSoon(int $days = 90): bool
    {
        if (!$this->expires_at) {
            return false;
        }
        return $this->expires_at->isFuture() && $this->expires_at->diffInDays(now()) <= $days;
    }

    /**
     * Retourne le nombre de jours avant expiration (null si pas de date)
     */
    public function getDaysUntilExpirationAttribute(): ?int
    {
        if (!$this->expires_at) {
            return null;
        }
        if ($this->isExpired()) {
            return -$this->expires_at->diffInDays(now());
        }
        return $this->expires_at->diffInDays(now());
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

    /**
     * Relation many-to-many vers les requirements (pour documents signés liés)
     */
    public function linkedRequirements(): BelongsToMany
    {
        return $this->belongsToMany(
            ComplianceRequirement::class,
            'compliance_document_requirements',
            'document_id',
            'requirement_id'
        )->withPivot('status', 'validated_at', 'validated_by')
         ->withTimestamps();
    }

    /**
     * Vérifie si ce document est un document signé (taggable)
     */
    public function isSignedDocument(): bool
    {
        return $this->document_type === 'signed_document';
    }

    /**
     * Retourne le label d'affichage (custom_label si défini, sinon file_name)
     */
    public function getDisplayLabelAttribute(): string
    {
        return $this->custom_label ?: $this->file_name;
    }
}
