<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Conjoint API Resource
 *
 * @property-read \App\Models\Conjoint $resource
 */
class ConjointResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'nom_jeune_fille' => $this->nom_jeune_fille,
            'prenom' => $this->prenom,
            'nom_complet' => $this->prenom.' '.strtoupper($this->nom ?? ''),
            'date_naissance' => $this->date_naissance,
            'lieu_naissance' => $this->lieu_naissance,
            'nationalite' => $this->nationalite,
            'profession' => $this->profession,
            'situation_actuelle_statut' => $this->situation_actuelle_statut,
            'telephone' => $this->telephone,
            'adresse' => $this->adresse,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
