<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;

/**
 * Client Controller
 *
 * Gère les clients selon les conventions Laravel Boost
 */
class ClientController extends Controller
{
    /**
     * Liste tous les clients de l'utilisateur connecté
     */
    public function index(): AnonymousResourceCollection
    {
        $clients = Client::with(['conjoint', 'enfants'])
            ->latest('id')
            ->get();

        return ClientResource::collection($clients);
    }

    /**
     * Affiche un client spécifique
     */
    public function show(int $id): ClientResource
    {
        $client = Client::with([
            'conjoint',
            'enfants',
            'santeSouhait',
            'baePrevoyance',
            'baeRetraite',
            'baeEpargne',
            'revenus',
            'passifs',
            'actifsFinanciers',
            'biensImmobiliers',
            'autresEpargnes',
        ])
            ->findOrFail($id);

        return ClientResource::make($client);
    }

    /**
     * Crée un nouveau client
     */
    public function store(StoreClientRequest $request): JsonResponse
    {
        $this->authorize('create', Client::class);

        $client = Client::create(
            array_merge($request->validated(), [
                'user_id' => auth()->id(),
                'team_id' => 1, // Team par défaut pour beta
            ])
        );

        return ClientResource::make($client)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Met à jour un client existant
     */
    public function update(UpdateClientRequest $request, int $id): ClientResource
    {
        $client = Client::findOrFail($id);

        $this->authorize('update', $client);

        $client->update($request->validated());

        // Créer automatiquement les sections BAE en fonction des besoins
        if ($request->has('besoins')) {
            $this->createBaeSectionsFromBesoins($client, $request->besoins);
        }

        return ClientResource::make($client->fresh([
            'conjoint',
            'enfants',
            'santeSouhait',
            'baePrevoyance',
            'baeRetraite',
            'baeEpargne',
            'revenus',
            'passifs',
            'actifsFinanciers',
            'biensImmobiliers',
            'autresEpargnes',
        ]));
    }

    /**
     * Crée automatiquement les sections BAE en fonction des besoins du client
     */
    private function createBaeSectionsFromBesoins($client, array $besoins): void
    {
        $besoinsLower = array_map('strtolower', $besoins);

        // Santé
        if (in_array('santé', $besoinsLower) || in_array('sante', $besoinsLower)) {
            if (!$client->santeSouhait) {
                $client->santeSouhait()->create([]);
                Log::info("Section santé créée automatiquement pour le client #{$client->id}");
            }
        }

        // Prévoyance
        if (in_array('prévoyance', $besoinsLower) || in_array('prevoyance', $besoinsLower)) {
            if (!$client->baePrevoyance) {
                $client->baePrevoyance()->create([]);
                Log::info("Section prévoyance créée automatiquement pour le client #{$client->id}");
            }
        }

        // Retraite
        if (in_array('retraite', $besoinsLower)) {
            if (!$client->baeRetraite) {
                $client->baeRetraite()->create([]);
                Log::info("Section retraite créée automatiquement pour le client #{$client->id}");
            }
        }

        // Épargne
        if (in_array('épargne', $besoinsLower) || in_array('epargne', $besoinsLower)) {
            if (!$client->baeEpargne) {
                $client->baeEpargne()->create([]);
                Log::info("Section épargne créée automatiquement pour le client #{$client->id}");
            }
        }
    }

    /**
     * Supprime un client
     */
    public function destroy(int $id): JsonResponse
    {
        $client = Client::findOrFail($id);

        $this->authorize('delete', $client);

        $client->delete();

        return response()->json(null, 204);
    }

    // ===== GESTION DES REVENUS =====

    public function storeRevenu(\Illuminate\Http\Request $request, Client $client): JsonResponse
    {
        $this->authorize('update', $client);

        $validated = $request->validate([
            'nature' => 'nullable|string|max:255',
            'periodicite' => 'nullable|string|max:50',
            'montant' => 'nullable|numeric|min:0',
        ]);

        $revenu = $client->revenus()->create($validated);

        return response()->json($revenu, 201);
    }

    public function updateRevenu(\Illuminate\Http\Request $request, Client $client, int $revenu): JsonResponse
    {
        $this->authorize('update', $client);

        $revenuModel = $client->revenus()->findOrFail($revenu);

        $validated = $request->validate([
            'nature' => 'nullable|string|max:255',
            'periodicite' => 'nullable|string|max:50',
            'montant' => 'nullable|numeric|min:0',
        ]);

        $revenuModel->update($validated);

        return response()->json($revenuModel);
    }

    public function deleteRevenu(Client $client, int $revenu): JsonResponse
    {
        $this->authorize('update', $client);

        $revenuModel = $client->revenus()->findOrFail($revenu);
        $revenuModel->delete();

        return response()->json(null, 204);
    }

    // ===== GESTION DES PASSIFS =====

    public function storePassif(\Illuminate\Http\Request $request, Client $client): JsonResponse
    {
        $this->authorize('update', $client);

        $validated = $request->validate([
            'nature' => 'nullable|string|max:255',
            'preteur' => 'nullable|string|max:255',
            'periodicite' => 'nullable|string|max:50',
            'montant_remboursement' => 'nullable|numeric|min:0',
            'capital_restant_du' => 'nullable|numeric|min:0',
            'duree_restante' => 'nullable|integer|min:0',
        ]);

        $passif = $client->passifs()->create($validated);

        return response()->json($passif, 201);
    }

    public function updatePassif(\Illuminate\Http\Request $request, Client $client, int $passif): JsonResponse
    {
        $this->authorize('update', $client);

        $passifModel = $client->passifs()->findOrFail($passif);

        $validated = $request->validate([
            'nature' => 'nullable|string|max:255',
            'preteur' => 'nullable|string|max:255',
            'periodicite' => 'nullable|string|max:50',
            'montant_remboursement' => 'nullable|numeric|min:0',
            'capital_restant_du' => 'nullable|numeric|min:0',
            'duree_restante' => 'nullable|integer|min:0',
        ]);

        $passifModel->update($validated);

        return response()->json($passifModel);
    }

    public function deletePassif(Client $client, int $passif): JsonResponse
    {
        $this->authorize('update', $client);

        $passifModel = $client->passifs()->findOrFail($passif);
        $passifModel->delete();

        return response()->json(null, 204);
    }

    // ===== GESTION DES ACTIFS FINANCIERS =====

    public function storeActifFinancier(\Illuminate\Http\Request $request, Client $client): JsonResponse
    {
        $this->authorize('update', $client);

        $validated = $request->validate([
            'nature' => 'nullable|string|max:255',
            'etablissement' => 'nullable|string|max:255',
            'detenteur' => 'nullable|string|max:255',
            'date_ouverture_souscription' => 'nullable|date',
            'valeur_actuelle' => 'nullable|numeric|min:0',
        ]);

        $actif = $client->actifsFinanciers()->create($validated);

        return response()->json($actif, 201);
    }

    public function updateActifFinancier(\Illuminate\Http\Request $request, Client $client, int $actifFinancier): JsonResponse
    {
        $this->authorize('update', $client);

        $actifModel = $client->actifsFinanciers()->findOrFail($actifFinancier);

        $validated = $request->validate([
            'nature' => 'nullable|string|max:255',
            'etablissement' => 'nullable|string|max:255',
            'detenteur' => 'nullable|string|max:255',
            'date_ouverture_souscription' => 'nullable|date',
            'valeur_actuelle' => 'nullable|numeric|min:0',
        ]);

        $actifModel->update($validated);

        return response()->json($actifModel);
    }

    public function deleteActifFinancier(Client $client, int $actifFinancier): JsonResponse
    {
        $this->authorize('update', $client);

        $actifModel = $client->actifsFinanciers()->findOrFail($actifFinancier);
        $actifModel->delete();

        return response()->json(null, 204);
    }

    // ===== GESTION DES BIENS IMMOBILIERS =====

    public function storeBienImmobilier(\Illuminate\Http\Request $request, Client $client): JsonResponse
    {
        $this->authorize('update', $client);

        $validated = $request->validate([
            'designation' => 'nullable|string|max:255',
            'detenteur' => 'nullable|string|max:255',
            'forme_propriete' => 'nullable|string|max:255',
            'valeur_actuelle_estimee' => 'nullable|numeric|min:0',
            'annee_acquisition' => 'nullable|integer|min:1900|max:' . date('Y'),
            'valeur_acquisition' => 'nullable|numeric|min:0',
        ]);

        $bien = $client->biensImmobiliers()->create($validated);

        return response()->json($bien, 201);
    }

    public function updateBienImmobilier(\Illuminate\Http\Request $request, Client $client, int $bienImmobilier): JsonResponse
    {
        $this->authorize('update', $client);

        $bienModel = $client->biensImmobiliers()->findOrFail($bienImmobilier);

        $validated = $request->validate([
            'designation' => 'nullable|string|max:255',
            'detenteur' => 'nullable|string|max:255',
            'forme_propriete' => 'nullable|string|max:255',
            'valeur_actuelle_estimee' => 'nullable|numeric|min:0',
            'annee_acquisition' => 'nullable|integer|min:1900|max:' . date('Y'),
            'valeur_acquisition' => 'nullable|numeric|min:0',
        ]);

        $bienModel->update($validated);

        return response()->json($bienModel);
    }

    public function deleteBienImmobilier(Client $client, int $bienImmobilier): JsonResponse
    {
        $this->authorize('update', $client);

        $bienModel = $client->biensImmobiliers()->findOrFail($bienImmobilier);
        $bienModel->delete();

        return response()->json(null, 204);
    }

    // ===== GESTION DES AUTRES ÉPARGNES =====

    public function storeAutreEpargne(\Illuminate\Http\Request $request, Client $client): JsonResponse
    {
        $this->authorize('update', $client);

        $validated = $request->validate([
            'designation' => 'nullable|string|max:255',
            'detenteur' => 'nullable|string|max:255',
            'valeur' => 'nullable|numeric|min:0',
        ]);

        $epargne = $client->autresEpargnes()->create($validated);

        return response()->json($epargne, 201);
    }

    public function updateAutreEpargne(\Illuminate\Http\Request $request, Client $client, int $autreEpargne): JsonResponse
    {
        $this->authorize('update', $client);

        $epargneModel = $client->autresEpargnes()->findOrFail($autreEpargne);

        $validated = $request->validate([
            'designation' => 'nullable|string|max:255',
            'detenteur' => 'nullable|string|max:255',
            'valeur' => 'nullable|numeric|min:0',
        ]);

        $epargneModel->update($validated);

        return response()->json($epargneModel);
    }

    public function deleteAutreEpargne(Client $client, int $autreEpargne): JsonResponse
    {
        $this->authorize('update', $client);

        $epargneModel = $client->autresEpargnes()->findOrFail($autreEpargne);
        $epargneModel->delete();

        return response()->json(null, 204);
    }
}
