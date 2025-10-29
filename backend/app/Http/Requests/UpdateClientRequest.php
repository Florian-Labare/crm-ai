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
            'nom' => 'sometimes|string|max:255',
            'prenom' => 'sometimes|string|max:255',
            'datedenaissance' => 'sometimes|string|max:255',
            'lieudenaissance' => 'sometimes|string|max:255',
            'situationmatrimoniale' => 'sometimes|string|max:255',
            'profession' => 'sometimes|string|max:255',
            'revenusannuels' => 'sometimes|string|max:255',
            'nombreenfants' => 'sometimes|integer',
            'besoins' => 'sometimes|string|max:500',
        ];
    }
}