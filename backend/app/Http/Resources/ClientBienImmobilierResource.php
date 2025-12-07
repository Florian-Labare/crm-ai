<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientBienImmobilierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'designation' => $this->designation,
            'detenteur' => $this->detenteur,
            'forme_propriete' => $this->forme_propriete,
            'valeur_actuelle_estimee' => $this->valeur_actuelle_estimee,
            'annee_acquisition' => $this->annee_acquisition,
            'valeur_acquisition' => $this->valeur_acquisition,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
