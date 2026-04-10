<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BaeRetraiteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'revenus_annuels' => $this->revenus_annuels,
            'revenus_annuels_foyer' => $this->revenus_annuels_foyer,
            'impot_revenu' => $this->impot_revenu,
            'nombre_parts_fiscales' => $this->nombre_parts_fiscales,
            'tmi' => $this->tmi,
            'impot_paye_n_1' => $this->impot_paye_n_1,
            'age_depart_retraite' => $this->age_depart_retraite,
            'age_depart_retraite_conjoint' => $this->age_depart_retraite_conjoint,
            'pourcentage_revenu_a_maintenir' => $this->pourcentage_revenu_a_maintenir,
            'contrat_en_place' => $this->contrat_en_place,
            'bilan_retraite_disponible' => $this->bilan_retraite_disponible,
            'complementaire_retraite_mise_en_place' => $this->complementaire_retraite_mise_en_place,
            'designation_etablissement' => $this->designation_etablissement,
            'cotisations_annuelles' => $this->cotisations_annuelles,
            'titulaire' => $this->titulaire,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
