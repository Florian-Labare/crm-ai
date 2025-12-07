<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Client API Resource
 *
 * Formate les données d'un client pour l'API selon les conventions Laravel Boost
 *
 * @property-read \App\Models\Client $resource
 */
class ClientResource extends JsonResource
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

            // Informations personnelles
            'civilite' => $this->civilite,
            'nom' => $this->nom,
            'nom_jeune_fille' => $this->nom_jeune_fille,
            'prenom' => $this->prenom,
            'nom_complet' => $this->prenom . ' ' . strtoupper($this->nom ?? ''),

            // Dates et lieux
            'date_naissance' => $this->date_naissance,
            'lieu_naissance' => $this->lieu_naissance,
            'nationalite' => $this->nationalite,

            // Situation
            'situation_matrimoniale' => $this->situation_matrimoniale,
            'date_situation_matrimoniale' => $this->date_situation_matrimoniale,
            'situation_actuelle' => $this->situation_actuelle,

            // Professionnel
            'profession' => $this->profession,
            'date_evenement_professionnel' => $this->date_evenement_professionnel,
            'risques_professionnels' => $this->risques_professionnels,
            'details_risques_professionnels' => $this->details_risques_professionnels,
            'revenus_annuels' => $this->revenus_annuels,

            // Coordonnées
            'adresse' => $this->adresse,
            'code_postal' => $this->code_postal,
            'ville' => $this->ville,
            'residence_fiscale' => $this->residence_fiscale,
            'telephone' => $this->telephone,
            'email' => $this->email,

            // Mode de vie
            'fumeur' => $this->fumeur,
            'activites_sportives' => $this->activites_sportives,
            'details_activites_sportives' => $this->details_activites_sportives,
            'niveau_activites_sportives' => $this->niveau_activites_sportives,

            // Famille
            'nombre_enfants' => $this->nombre_enfants,

            // Entreprise
            'chef_entreprise' => $this->chef_entreprise,
            'statut' => $this->statut,
            'travailleur_independant' => $this->travailleur_independant,
            'mandataire_social' => $this->mandataire_social,

            // Besoins et consentement
            'besoins' => $this->besoins,
            'consentement_audio' => $this->consentement_audio,
            'charge_clientele' => $this->charge_clientele,

            // Relations (conditionnelles - chargées uniquement si eager loaded)
            'conjoint' => new ConjointResource($this->whenLoaded('conjoint')),
            'enfants' => EnfantResource::collection($this->whenLoaded('enfants')),
            'sante_souhait' => new SanteSouhaitResource($this->whenLoaded('santeSouhait')),
            'bae_prevoyance' => new BaePrevoyanceResource($this->whenLoaded('baePrevoyance')),
            'bae_retraite' => new BaeRetraiteResource($this->whenLoaded('baeRetraite')),
            'bae_epargne' => new BaeEpargneResource($this->whenLoaded('baeEpargne')),
            'revenus' => ClientRevenuResource::collection($this->whenLoaded('revenus')),
            'passifs' => ClientPassifResource::collection($this->whenLoaded('passifs')),
            'actifs_financiers' => ClientActifFinancierResource::collection($this->whenLoaded('actifsFinanciers')),
            'biens_immobiliers' => ClientBienImmobilierResource::collection($this->whenLoaded('biensImmobiliers')),
            'autres_epargnes' => ClientAutreEpargneResource::collection($this->whenLoaded('autresEpargnes')),

            // Métadonnées
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
