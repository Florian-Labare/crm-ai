<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Store Chunk Request
 *
 * Validation pour l'upload d'un chunk audio (max 10 minutes)
 */
class StoreChunkRequest extends FormRequest
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
            'session_id' => ['required', 'uuid'],
            'part_index' => ['required', 'integer', 'min:0'],
            'audio' => ['required', 'file', 'mimes:webm,wav,mp3,m4a', 'max:102400'], // 100MB max
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
        ];
    }
}
