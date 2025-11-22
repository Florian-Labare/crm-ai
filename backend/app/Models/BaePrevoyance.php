<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BaePrevoyance extends Model
{
    use HasFactory;

    protected $table = 'bae_prevoyance';

    protected $fillable = [
        'client_id',
        'contrat_en_place',
        'date_effet',
        'cotisations',
        'souhaite_couverture_invalidite',
        'revenu_a_garantir',
        'souhaite_couvrir_charges_professionnelles',
        'montant_annuel_charges_professionnelles',
        'garantir_totalite_charges_professionnelles',
        'montant_charges_professionnelles_a_garantir',
        'duree_indemnisation_souhaitee',
        'capital_deces_souhaite',
        'garanties_obseques',
        'rente_enfants',
        'rente_conjoint',
        'payeur',
    ];

    protected $casts = [
        'date_effet' => 'date',
        'cotisations' => 'decimal:2',
        'souhaite_couverture_invalidite' => 'boolean',
        'revenu_a_garantir' => 'decimal:2',
        'souhaite_couvrir_charges_professionnelles' => 'boolean',
        'montant_annuel_charges_professionnelles' => 'decimal:2',
        'garantir_totalite_charges_professionnelles' => 'boolean',
        'montant_charges_professionnelles_a_garantir' => 'decimal:2',
        'capital_deces_souhaite' => 'decimal:2',
        'garanties_obseques' => 'decimal:2',
        'rente_enfants' => 'decimal:2',
        'rente_conjoint' => 'decimal:2',
    ];

    /**
     * Relation avec le client
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
