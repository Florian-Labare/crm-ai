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
            'nom' => 'required|string|max:255',
            'prenom' => 'nullable|string|max:255',
            'datedenaissance' => 'nullable|string|max:255',
            'lieudenaissance' => 'nullable|string|max:255',
            'situationmatrimoniale' => 'nullable|string|max:255',
            'profession' => 'nullable|string|max:255',
            'revenusannuels' => 'nullable|string|max:255',
            'nombreenfants' => 'nullable|integer',
            'besoins' => 'nullable|string|max:500',
        ];
    }
}
