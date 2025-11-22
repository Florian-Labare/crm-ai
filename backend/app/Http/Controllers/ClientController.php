<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;

class ClientController extends Controller
{
    public function index(): JsonResponse
    {
        // Filtrer uniquement les clients de l'utilisateur connecté
        $clients = Client::where('user_id', auth()->id())
            ->orderByDesc('id')
            ->get();

        return response()->json($clients);
    }

    public function show(int $id): JsonResponse
    {
        $client = Client::with(['conjoint', 'enfants', 'santeSouhait', 'baePrevoyance', 'baeRetraite', 'baeEpargne'])
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        return response()->json($client);
    }

    public function store(StoreClientRequest $request): JsonResponse
    {
        // Ajouter automatiquement l'user_id de l'utilisateur connecté
        $data = array_merge($request->validated(), [
            'user_id' => auth()->id()
        ]);

        $client = Client::create($data);
        return response()->json($client, 201);
    }

    public function update(UpdateClientRequest $request, int $id): JsonResponse
    {
        // Vérifier que le client appartient bien à l'utilisateur connecté
        $client = Client::where('user_id', auth()->id())->findOrFail($id);
        $client->update($request->validated());

        return response()->json($client);
    }

    public function destroy(int $id): JsonResponse
    {
        // Vérifier que le client appartient bien à l'utilisateur connecté
        $client = Client::where('user_id', auth()->id())->findOrFail($id);
        $client->delete();

        return response()->json(null, 204);
    }
}
