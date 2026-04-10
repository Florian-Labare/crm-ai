<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BaeEpargne extends Model
{
    use HasFactory;

    protected $table = 'bae_epargne';

    protected $fillable = [
        'client_id',
        'epargne_disponible',
        'montant_epargne_disponible',
        'donation_realisee',
        'donation_forme',
        'donation_date',
        'donation_montant',
        'donation_beneficiaires',
        'capacite_epargne_estimee',
        'actifs_financiers_pourcentage',
        'actifs_financiers_total',
        'actifs_financiers_details',
        'actifs_immo_pourcentage',
        'actifs_immo_total',
        'actifs_immo_details',
        'actifs_autres_pourcentage',
        'actifs_autres_total',
        'actifs_autres_details',
        'passifs_total_emprunts',
        'passifs_details',
        'charges_totales',
        'charges_details',
        'situation_financiere_revenus_charges',
    ];

    protected $casts = [
        'epargne_disponible' => 'boolean',
        'montant_epargne_disponible' => 'decimal:2',
        'donation_realisee' => 'boolean',
        'donation_date' => 'date',
        'donation_montant' => 'decimal:2',
        'capacite_epargne_estimee' => 'decimal:2',
        'actifs_financiers_pourcentage' => 'decimal:2',
        'actifs_financiers_total' => 'decimal:2',
        'actifs_financiers_details' => 'array',
        'actifs_immo_pourcentage' => 'decimal:2',
        'actifs_immo_total' => 'decimal:2',
        'actifs_immo_details' => 'array',
        'actifs_autres_pourcentage' => 'decimal:2',
        'actifs_autres_total' => 'decimal:2',
        'actifs_autres_details' => 'array',
        'passifs_total_emprunts' => 'decimal:2',
        'passifs_details' => 'array',
        'charges_totales' => 'decimal:2',
        'charges_details' => 'array',
    ];

    /**
     * Relation avec le client
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
