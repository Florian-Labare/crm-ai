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
        return response()->json(Client::orderByDesc('id')->get());
    }

    public function show(int $id): JsonResponse
    {
        $client = Client::with(['conjoint', 'enfants', 'entreprise', 'santeSouhait'])
            ->findOrFail($id);
        return response()->json($client);
    }

    public function store(StoreClientRequest $request): JsonResponse
    {
        $client = Client::create($request->validated());
        return response()->json($client, 201);
    }

    public function update(UpdateClientRequest $request, int $id): JsonResponse
    {
        $client = Client::findOrFail($id);
        $client->update($request->validated());
        return response()->json($client);
    }

    public function destroy(int $id): JsonResponse
    {
        $client = Client::findOrFail($id);
        $client->delete();
        return response()->json(null, 204);
    }
}
