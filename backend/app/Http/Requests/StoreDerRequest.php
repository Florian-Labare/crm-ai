<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Store DER Request
 *
 * Validation pour la création d'un rendez-vous et envoi du DER
 */
class StoreDerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Accessible aux utilisateurs authentifiés
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'charge_clientele_id' => ['required', 'exists:users,id'],
            'civilite' => ['required', 'in:Monsieur,Madame'],
            'nom' => ['required', 'string', 'max:255'],
            'prenom' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:clients,email'],
            'lieu_rdv' => ['required', 'string', 'max:255'],
            'date_rdv' => ['required', 'date', 'after_or_equal:today'],
            'heure_rdv' => ['required', 'date_format:H:i'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'charge_clientele_id' => 'chargé de clientèle',
            'civilite' => 'civilité',
            'nom' => 'nom',
            'prenom' => 'prénom',
            'email' => 'adresse mail',
            'lieu_rdv' => 'lieu du rendez-vous',
            'date_rdv' => 'date du rendez-vous',
            'heure_rdv' => 'heure du rendez-vous',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'charge_clientele_id.required' => 'Veuillez sélectionner un chargé de clientèle.',
            'charge_clientele_id.exists' => 'Le chargé de clientèle sélectionné n\'existe pas.',
            'email.unique' => 'Un prospect avec cet email existe déjà.',
            'date_rdv.after_or_equal' => 'La date du rendez-vous ne peut pas être dans le passé.',
            'heure_rdv.date_format' => 'Le format de l\'heure doit être HH:MM.',
        ];
    }
}
