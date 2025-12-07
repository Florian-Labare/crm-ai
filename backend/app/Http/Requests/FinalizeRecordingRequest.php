<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Finalize Recording Request
 *
 * Validation pour finaliser une session d'enregistrement
 */
class FinalizeRecordingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // L'authentification est gérée par Sanctum
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
}
