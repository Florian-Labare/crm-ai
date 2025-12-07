<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SanteSouhaitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'contrat_en_place' => $this->contrat_en_place,
            'budget_mensuel_maximum' => $this->budget_mensuel_maximum,
            'niveau_hospitalisation' => $this->niveau_hospitalisation,
            'niveau_chambre_particuliere' => $this->niveau_chambre_particuliere,
            'niveau_medecin_generaliste' => $this->niveau_medecin_generaliste,
            'niveau_analyses_imagerie' => $this->niveau_analyses_imagerie,
            'niveau_auxiliaires_medicaux' => $this->niveau_auxiliaires_medicaux,
            'niveau_pharmacie' => $this->niveau_pharmacie,
            'niveau_dentaire' => $this->niveau_dentaire,
            'niveau_optique' => $this->niveau_optique,
            'niveau_protheses_auditives' => $this->niveau_protheses_auditives,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
