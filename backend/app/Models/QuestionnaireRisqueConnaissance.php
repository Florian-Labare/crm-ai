<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionnaireRisqueConnaissance extends Model
{
    protected $fillable = [
        'questionnaire_risque_id',
        'connaissance_obligations',
        'montant_obligations',
        'connaissance_actions',
        'montant_actions',
        'connaissance_fip_fcpi',
        'montant_fip_fcpi',
        'connaissance_opci_scpi',
        'montant_opci_scpi',
        'connaissance_produits_structures',
        'montant_produits_structures',
        'connaissance_monetaires',
        'montant_monetaires',
        'connaissance_parts_sociales',
        'montant_parts_sociales',
        'connaissance_titres_participatifs',
        'montant_titres_participatifs',
        'connaissance_fps_slp',
        'montant_fps_slp',
        'connaissance_girardin',
        'montant_girardin',
    ];

    protected $casts = [
        'connaissance_obligations' => 'boolean',
        'connaissance_actions' => 'boolean',
        'connaissance_fip_fcpi' => 'boolean',
        'connaissance_opci_scpi' => 'boolean',
        'connaissance_produits_structures' => 'boolean',
        'connaissance_monetaires' => 'boolean',
        'connaissance_parts_sociales' => 'boolean',
        'connaissance_titres_participatifs' => 'boolean',
        'connaissance_fps_slp' => 'boolean',
        'connaissance_girardin' => 'boolean',
    ];

    public function questionnaireRisque(): BelongsTo
    {
        return $this->belongsTo(QuestionnaireRisque::class);
    }
}
