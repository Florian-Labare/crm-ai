<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BaeRetraite extends Model
{
    use HasFactory;

    protected $table = 'bae_retraite';

    protected $fillable = [
        'client_id',
        'revenus_annuels',
        'revenus_annuels_foyer',
        'impot_revenu',
        'nombre_parts_fiscales',
        'tmi',
        'impot_paye_n_1',
        'age_depart_retraite',
        'age_depart_retraite_conjoint',
        'pourcentage_revenu_a_maintenir',
        'contrat_en_place',
        'bilan_retraite_disponible',
        'complementaire_retraite_mise_en_place',
        'designation_etablissement',
        'cotisations_annuelles',
        'titulaire',
    ];

    protected $casts = [
        'revenus_annuels' => 'decimal:2',
        'revenus_annuels_foyer' => 'decimal:2',
        'impot_revenu' => 'decimal:2',
        'nombre_parts_fiscales' => 'decimal:2',
        'impot_paye_n_1' => 'decimal:2',
        'age_depart_retraite' => 'integer',
        'age_depart_retraite_conjoint' => 'integer',
        'pourcentage_revenu_a_maintenir' => 'decimal:2',
        'bilan_retraite_disponible' => 'boolean',
        'complementaire_retraite_mise_en_place' => 'boolean',
        'cotisations_annuelles' => 'decimal:2',
    ];

    /**
     * Relation avec le client
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
