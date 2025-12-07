<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BaePrevoyanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'contrat_en_place' => $this->contrat_en_place,
            'date_effet' => $this->date_effet,
            'cotisations' => $this->cotisations,
            'souhaite_couverture_invalidite' => $this->souhaite_couverture_invalidite,
            'revenu_a_garantir' => $this->revenu_a_garantir,
            'souhaite_couvrir_charges_professionnelles' => $this->souhaite_couvrir_charges_professionnelles,
            'montant_annuel_charges_professionnelles' => $this->montant_annuel_charges_professionnelles,
            'garantir_totalite_charges_professionnelles' => $this->garantir_totalite_charges_professionnelles,
            'montant_charges_professionnelles_a_garantir' => $this->montant_charges_professionnelles_a_garantir,
            'duree_indemnisation_souhaitee' => $this->duree_indemnisation_souhaitee,
            'capital_deces_souhaite' => $this->capital_deces_souhaite,
            'garanties_obseques' => $this->garanties_obseques,
            'rente_enfants' => $this->rente_enfants,
            'rente_conjoint' => $this->rente_conjoint,
            'payeur' => $this->payeur,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
