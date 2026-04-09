<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientActifFinancierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'nature' => $this->nature,
            'etablissement' => $this->etablissement,
            'detenteur' => $this->detenteur,
            'date_ouverture_souscription' => $this->date_ouverture_souscription,
            'valeur_actuelle' => $this->valeur_actuelle,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
