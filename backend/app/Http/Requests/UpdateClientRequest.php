<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'civilite' => 'sometimes|string|max:255',
            'nom' => 'sometimes|string|max:255',
            'nom_jeune_fille' => 'sometimes|nullable|string|max:255',
            'prenom' => 'sometimes|string|max:255',
            'date_naissance' => 'sometimes|nullable|string|max:255',
            'lieu_naissance' => 'sometimes|nullable|string|max:255',
            'nationalite' => 'sometimes|nullable|string|max:255',
            'situation_matrimoniale' => 'sometimes|nullable|string|max:255',
            'date_situation_matrimoniale' => 'sometimes|nullable|string|max:255',
            'situation_actuelle' => 'sometimes|nullable|string|max:255',
            'profession' => 'sometimes|nullable|string|max:255',
            'date_evenement_professionnel' => 'sometimes|nullable|string|max:255',
            'risques_professionnels' => 'sometimes|nullable|boolean',
            'details_risques_professionnels' => 'sometimes|nullable|string',
            'revenus_annuels' => 'sometimes|nullable|string|max:255',
            'adresse' => 'sometimes|nullable|string|max:255',
            'code_postal' => 'sometimes|nullable|string|max:10',
            'ville' => 'sometimes|nullable|string|max:255',
            'residence_fiscale' => 'sometimes|nullable|string|max:255',
            'telephone' => 'sometimes|nullable|string|max:20',
            'email' => 'sometimes|nullable|email|max:255',
            'fumeur' => 'sometimes|nullable|boolean',
            'activites_sportives' => 'sometimes|nullable|boolean',
            'details_activites_sportives' => 'sometimes|nullable|string',
            'niveau_activites_sportives' => 'sometimes|nullable|string|max:255',
            'nombre_enfants' => 'sometimes|nullable|integer',
            'besoins' => 'sometimes|nullable|array',
            'besoins.*' => 'string|max:255',
            'charge_clientele' => 'sometimes|nullable|string|max:255',
            'chef_entreprise' => 'sometimes|nullable|boolean',
            'statut' => 'sometimes|nullable|string|max:255',
            'travailleur_independant' => 'sometimes|nullable|boolean',
            'mandataire_social' => 'sometimes|nullable|boolean',
        ];
    }
}
