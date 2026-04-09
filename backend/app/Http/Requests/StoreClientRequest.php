<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'civilite' => 'nullable|string|max:255',
            'nom' => 'required|string|max:255',
            'nom_jeune_fille' => 'nullable|string|max:255',
            'prenom' => 'nullable|string|max:255',
            'date_naissance' => 'nullable|string|max:255',
            'lieu_naissance' => 'nullable|string|max:255',
            'nationalite' => 'nullable|string|max:255',
            'situation_matrimoniale' => 'nullable|string|max:255',
            'date_situation_matrimoniale' => 'nullable|string|max:255',
            'situation_actuelle' => 'nullable|string|max:255',
            'profession' => 'nullable|string|max:255',
            'date_evenement_professionnel' => 'nullable|string|max:255',
            'risques_professionnels' => 'nullable|boolean',
            'details_risques_professionnels' => 'nullable|string',
            'revenus_annuels' => 'nullable|string|max:255',
            'adresse' => 'nullable|string|max:255',
            'code_postal' => 'nullable|string|max:10',
            'ville' => 'nullable|string|max:255',
            'residence_fiscale' => 'nullable|string|max:255',
            'telephone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'fumeur' => 'nullable|boolean',
            'activites_sportives' => 'nullable|boolean',
            'details_activites_sportives' => 'nullable|string',
            'niveau_activites_sportives' => 'nullable|string|max:255',
            'nombre_enfants' => 'nullable|integer',
            'besoins' => 'nullable|array',
            'besoins.*' => 'string|max:255',
            'charge_clientele' => 'nullable|string|max:255',
            'chef_entreprise' => 'nullable|boolean',
            'statut' => 'nullable|string|max:255',
            'travailleur_independant' => 'nullable|boolean',
            'mandataire_social' => 'nullable|boolean',
        ];
    }
}
