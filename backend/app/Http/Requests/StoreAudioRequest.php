<?php

namespace App\Http\Requests;

use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Store Audio Request
 *
 * Validation pour l'upload d'un fichier audio
 * Inclut la validation de l'appartenance du client à la team de l'utilisateur
 */
class StoreAudioRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // L'utilisateur doit être authentifié et avoir une team
        $user = $this->user();
        if (!$user || !$user->currentTeam()) {
            return false;
        }

        // Si un client_id est fourni, vérifier qu'il appartient à la team
        $clientId = $this->input('client_id');
        if ($clientId) {
            $client = Client::find($clientId);
            if (!$client || $client->team_id !== $user->currentTeam()->id) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $teamId = $this->user()?->currentTeam()?->id;

        return [
            'audio' => [
                'required',
                'file',
                'mimes:mp3,wav,ogg,webm,m4a,mpeg',
                'max:20480', // 20 Mo max
            ],
            'client_id' => [
                'nullable',
                'integer',
                // Valider que le client existe ET appartient à la team de l'utilisateur
                function ($attribute, $value, $fail) use ($teamId) {
                    if ($value && $teamId) {
                        $exists = Client::where('id', $value)
                            ->where('team_id', $teamId)
                            ->exists();
                        if (!$exists) {
                            $fail('Le client spécifié n\'existe pas ou n\'appartient pas à votre équipe.');
                        }
                    }
                },
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'audio.required' => 'Le fichier audio est requis.',
            'audio.file' => 'Le fichier doit être un fichier audio valide.',
            'audio.mimes' => 'Le fichier doit être au format MP3, WAV, OGG, WEBM ou M4A.',
            'audio.max' => 'Le fichier ne doit pas dépasser 20 Mo.',
            'client_id.exists' => 'Le client spécifié n\'existe pas.',
        ];
    }

    /**
     * Prépare les données pour validation
     */
    protected function prepareForValidation(): void
    {
        // S'assurer que client_id est null si vide
        if ($this->client_id === '') {
            $this->merge(['client_id' => null]);
        }
    }
}
