<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Enfant API Resource
 *
 * @property-read \App\Models\Enfant $resource
 */
class EnfantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'prenom' => $this->prenom,
            'nom_complet' => trim($this->prenom.' '.($this->nom ?? '')),
            'date_naissance' => $this->date_naissance,
            'age' => $this->date_naissance ? now()->diffInYears($this->date_naissance) : null,
            'fiscalement_a_charge' => $this->fiscalement_a_charge,
            'garde_alternee' => $this->garde_alternee,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
