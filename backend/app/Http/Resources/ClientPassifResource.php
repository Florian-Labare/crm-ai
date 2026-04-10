<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientPassifResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'nature' => $this->nature,
            'preteur' => $this->preteur,
            'periodicite' => $this->periodicite,
            'montant_remboursement' => $this->montant_remboursement,
            'capital_restant_du' => $this->capital_restant_du,
            'duree_restante' => $this->duree_restante,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
