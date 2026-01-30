<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComplianceRequirement extends Model
{
    protected $fillable = [
        'besoin',
        'document_type',
        'document_label',
        'category',
        'is_mandatory',
        'priority',
    ];

    protected $casts = [
        'is_mandatory' => 'boolean',
        'priority' => 'integer',
    ];

    /**
     * Mapping des besoins vers les labels affichés
     */
    public const BESOIN_LABELS = [
        'prevoyance' => 'Prévoyance',
        'retraite' => 'Retraite',
        'epargne' => 'Épargne',
        'sante' => 'Santé',
        'immobilier' => 'Immobilier',
        'fiscalite' => 'Fiscalité',
        'global' => 'Documents généraux', // Pour CNI, avis d'imposition, etc.
    ];

    /**
     * Retourne les documents requis pour un ensemble de besoins
     */
    public static function getRequirementsForBesoins(array $besoins): \Illuminate\Database\Eloquent\Collection
    {
        // Toujours inclure les documents globaux (CNI, avis imposition)
        $besoins[] = 'global';

        return self::whereIn('besoin', $besoins)
            ->orderBy('priority')
            ->orderBy('besoin')
            ->get();
    }

    /**
     * Retourne le label du besoin
     */
    public function getBesoinLabelAttribute(): string
    {
        return self::BESOIN_LABELS[$this->besoin] ?? $this->besoin;
    }
}
