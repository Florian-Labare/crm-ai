<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BaeEpargneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'epargne_disponible' => $this->epargne_disponible,
            'montant_epargne_disponible' => $this->montant_epargne_disponible,
            'donation_realisee' => $this->donation_realisee,
            'donation_forme' => $this->donation_forme,
            'donation_date' => $this->donation_date,
            'donation_montant' => $this->donation_montant,
            'donation_beneficiaires' => $this->donation_beneficiaires,
            'capacite_epargne_estimee' => $this->capacite_epargne_estimee,
            'actifs_financiers_pourcentage' => $this->actifs_financiers_pourcentage,
            'actifs_financiers_total' => $this->actifs_financiers_total,
            'actifs_financiers_details' => $this->actifs_financiers_details,
            'actifs_immo_pourcentage' => $this->actifs_immo_pourcentage,
            'actifs_immo_total' => $this->actifs_immo_total,
            'actifs_immo_details' => $this->actifs_immo_details,
            'actifs_autres_pourcentage' => $this->actifs_autres_pourcentage,
            'actifs_autres_total' => $this->actifs_autres_total,
            'actifs_autres_details' => $this->actifs_autres_details,
            'passifs_total_emprunts' => $this->passifs_total_emprunts,
            'passifs_details' => $this->passifs_details,
            'charges_totales' => $this->charges_totales,
            'charges_details' => $this->charges_details,
            'situation_financiere_revenus_charges' => $this->situation_financiere_revenus_charges,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
