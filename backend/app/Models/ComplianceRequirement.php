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
        'emprunteur' => 'Emprunteur',
        'any_besoin' => 'Mandat & Mission',
        'global' => 'Documents généraux',
    ];

    /**
     * Retourne les documents requis pour un ensemble de besoins
     */
    public static function getRequirementsForBesoins(array $besoins): \Illuminate\Database\Eloquent\Collection
    {
        $toInclude = ['global'];
        if (!empty($besoins)) {
            $toInclude[] = 'any_besoin';
            $toInclude = array_merge($toInclude, $besoins);
        }

        return self::whereIn('besoin', $toInclude)
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
