<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Store Audio Request
 *
 * Validation pour l'upload d'un fichier audio
 */
class StoreAudioRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
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
                'exists:clients,id',
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
