<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionnaireRisqueFinancier extends Model
{
    protected $fillable = [
        'questionnaire_risque_id',
        'temps_attente_recuperation_valeur',
        'niveau_perte_inquietude',
        'reaction_baisse_25',
        'attitude_placements',
        'allocation_epargne',
        'objectif_placement',
        'placements_inquietude',
        'epargne_precaution',
        'reaction_moins_value',
        'impact_baisse_train_vie',
        'perte_supportable',
        'objectif_global',
        'objectifs_rapport',
        'horizon_investissement',
        'tolerance_risque',
        'niveau_connaissance_globale',
        'pourcentage_perte_max',
    ];

    protected $casts = [
        'placements_inquietude' => 'boolean',
        'epargne_precaution' => 'boolean',
    ];

    public function questionnaireRisque(): BelongsTo
    {
        return $this->belongsTo(QuestionnaireRisque::class);
    }
}
