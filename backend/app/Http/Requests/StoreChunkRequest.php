<?php

namespace App\Http\Requests;

use App\Models\Client;
use App\Models\RecordingSession;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Store Chunk Request
 *
 * Validation pour l'upload d'un chunk audio (max 10 minutes)
 * Inclut la validation de l'appartenance du client et de la session à la team
 */
class StoreChunkRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        if (!$user || !$user->currentTeam()) {
            return false;
        }

        $teamId = $user->currentTeam()->id;
        $sessionId = $this->input('session_id');

        // Vérifier si la session existe déjà
        $existingSession = RecordingSession::where('session_id', $sessionId)->first();

        if ($existingSession) {
            // La session existe : vérifier qu'elle appartient à l'utilisateur et à sa team
            if ($existingSession->user_id !== $user->id || $existingSession->team_id !== $teamId) {
                return false;
            }
        }

        // Si un client_id est fourni, vérifier qu'il appartient à la team
        $clientId = $this->input('client_id');
        if ($clientId) {
            $client = Client::find($clientId);
            if (!$client || $client->team_id !== $teamId) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $teamId = $this->user()?->currentTeam()?->id;

        return [
            'session_id' => ['required', 'uuid'],
            'part_index' => ['required', 'integer', 'min:0'],
            'audio' => ['required', 'file', 'mimes:webm,wav,mp3,m4a', 'max:102400'], // 100MB max
            'client_id' => [
                'nullable',
                'integer',
                // Valider que le client appartient à la team
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
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'session_id.required' => 'L\'identifiant de session est requis.',
            'session_id.uuid' => 'L\'identifiant de session doit être un UUID valide.',
            'audio.required' => 'Le fichier audio est requis.',
            'audio.max' => 'Le chunk audio ne doit pas dépasser 100 Mo.',
        ];
    }
}
