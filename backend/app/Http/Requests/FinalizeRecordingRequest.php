<?php

namespace App\Http\Requests;

use App\Models\RecordingSession;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Finalize Recording Request
 *
 * Validation pour finaliser une session d'enregistrement
 * Vérifie que la session appartient à l'utilisateur et sa team
 */
class FinalizeRecordingRequest extends FormRequest
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

        // Récupérer le session_id depuis la route
        $sessionId = $this->route('sessionId');
        if (!$sessionId) {
            return false;
        }

        // Vérifier que la session existe et appartient à l'utilisateur/team
        $session = RecordingSession::where('session_id', $sessionId)->first();

        if (!$session) {
            return false;
        }

        // Vérifier l'appartenance
        if ($session->user_id !== $user->id || $session->team_id !== $user->currentTeam()->id) {
            return false;
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Pas de règles supplémentaires, le session_id vient de l'URL
        ];
    }

    /**
     * Get the error message for authorization failure.
     */
    protected function failedAuthorization(): void
    {
        throw new \Illuminate\Auth\Access\AuthorizationException(
            'Cette session d\'enregistrement ne vous appartient pas ou n\'existe pas.'
        );
    }
}
