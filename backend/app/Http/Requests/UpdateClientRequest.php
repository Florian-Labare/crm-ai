<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Update Client Request
 *
 * Validation pour la mise à jour d'un client existant
 */
class UpdateClientRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // L'autorisation est gérée dans le contrôleur via where('user_id')
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Informations personnelles
            'civilite' => ['sometimes', 'in:Monsieur,Madame'],
            'nom' => ['sometimes', 'string', 'max:255'],
            'nom_jeune_fille' => ['nullable', 'string', 'max:255'],
            'prenom' => ['sometimes', 'string', 'max:255'],
            'date_naissance' => ['nullable', 'date'],
            'lieu_naissance' => ['nullable', 'string', 'max:255'],
            'nationalite' => ['nullable', 'string', 'max:255'],

            // Situation
            'situation_matrimoniale' => ['nullable', 'string'],
            'date_situation_matrimoniale' => ['nullable', 'date'],
            'situation_actuelle' => ['nullable', 'string'],

            // Professionnel
            'profession' => ['nullable', 'string', 'max:255'],
            'date_evenement_professionnel' => ['nullable', 'date'],
            'risques_professionnels' => ['sometimes', 'boolean'],
            'details_risques_professionnels' => ['nullable', 'string'],
            'revenus_annuels' => ['nullable', 'integer', 'min:0'],

            // Coordonnées
            'adresse' => ['nullable', 'string'],
            'code_postal' => ['nullable', 'string', 'max:10'],
            'ville' => ['nullable', 'string', 'max:255'],
            'residence_fiscale' => ['nullable', 'string', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],

            // Mode de vie
            'fumeur' => ['sometimes', 'boolean'],
            'activites_sportives' => ['sometimes', 'boolean'],
            'details_activites_sportives' => ['nullable', 'string'],
            'niveau_activites_sportives' => ['nullable', 'string'],

            // Famille
            'nombre_enfants' => ['nullable', 'integer', 'min:0'],

            // Entreprise
            'chef_entreprise' => ['sometimes', 'boolean'],
            'statut' => ['nullable', 'string', 'max:255'],
            'travailleur_independant' => ['sometimes', 'boolean'],
            'mandataire_social' => ['sometimes', 'boolean'],

            // Besoins
            'besoins' => ['nullable', 'array'],
            'besoins.*' => ['string', 'max:255'],
            'consentement_audio' => ['sometimes', 'boolean'],
            'charge_clientele' => ['nullable', 'string', 'max:255'],
        ];
    }
}
