<?php

namespace App\Http\Controllers;

use App\Models\Assureur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssureurController extends Controller {
    public function index(): JsonResponse {
        $assureurs = Assureur::orderBy('nom')->get(['id', 'nom', 'lien_espace_client']);

        return response()->json(['data' => $assureurs]);
    }

    public function updateLien(Request $request, Assureur $assureur): JsonResponse {
        $request->validate([
            'lien_espace_client' => 'nullable|url|max:500',
        ]);

        $assureur->update(['lien_espace_client' => $request->input('lien_espace_client')]);

        return response()->json(['data' => $assureur->only(['id', 'nom', 'lien_espace_client'])]);
    }
}
