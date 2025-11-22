<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SanteSouhait extends Model
{
    protected $fillable = [
        'client_id',
        'contrat_en_place',
        'budget_mensuel_maximum',
        'niveau_hospitalisation',
        'niveau_chambre_particuliere',
        'niveau_medecin_generaliste',
        'niveau_analyses_imagerie',
        'niveau_auxiliaires_medicaux',
        'niveau_pharmacie',
        'niveau_dentaire',
        'niveau_optique',
        'niveau_protheses_auditives',
    ];

    protected $casts = [
        'budget_mensuel_maximum' => 'decimal:2',
        'niveau_hospitalisation' => 'integer',
        'niveau_chambre_particuliere' => 'integer',
        'niveau_medecin_generaliste' => 'integer',
        'niveau_analyses_imagerie' => 'integer',
        'niveau_auxiliaires_medicaux' => 'integer',
        'niveau_pharmacie' => 'integer',
        'niveau_dentaire' => 'integer',
        'niveau_optique' => 'integer',
        'niveau_protheses_auditives' => 'integer',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
