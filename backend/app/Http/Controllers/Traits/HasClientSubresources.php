<?php

namespace App\Http\Controllers\Traits;

use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CRUD générique pour les sous-ressources d'un Client.
 *
 * Utilisé par ClientController pour éliminer la duplication
 * des blocs store/update/delete identiques.
 */
trait HasClientSubresources
{
    /**
     * Crée un item dans une relation HasMany du client.
     */
    protected function storeSubresource(
        Request $request,
        Client $client,
        string $relation,
        array $rules
    ): JsonResponse {
        $this->authorize('update', $client);
        $validated = $request->validate($rules);
        $item = $client->{$relation}()->create($validated);
        return response()->json($item, 201);
    }

    /**
     * Met à jour un item dans une relation HasMany du client.
     */
    protected function updateSubresource(
        Request $request,
        Client $client,
        string $relation,
        int $itemId,
        array $rules
    ): JsonResponse {
        $this->authorize('update', $client);
        $item = $client->{$relation}()->findOrFail($itemId);
        $validated = $request->validate($rules);
        $item->update($validated);
        return response()->json($item);
    }

    /**
     * Supprime un item dans une relation HasMany du client.
     */
    protected function deleteSubresource(
        Client $client,
        string $relation,
        int $itemId
    ): JsonResponse {
        $this->authorize('update', $client);
        $item = $client->{$relation}()->findOrFail($itemId);
        $item->delete();
        return response()->json(null, 204);
    }

    /**
     * Crée ou met à jour un singleton HasOne du client (upsert).
     */
    protected function upsertSingleton(
        Request $request,
        Client $client,
        string $relation,
        array $rules,
        ?callable $beforeValidate = null
    ): JsonResponse {
        $this->authorize('update', $client);

        if ($beforeValidate) {
            $beforeValidate($request);
        }

        $validated = $request->validate($rules);
        $existing  = $client->{$relation};

        if ($existing) {
            $existing->update($validated);
            return response()->json($existing);
        }

        $validated['client_id'] = $client->id;
        $item = $client->{$relation}()->create($validated);
        return response()->json($item, 201);
    }

    /**
     * Supprime un singleton HasOne du client.
     */
    protected function deleteSingleton(Client $client, string $relation): JsonResponse
    {
        $this->authorize('update', $client);
        $client->{$relation}?->delete();
        return response()->json(null, 204);
    }
}
