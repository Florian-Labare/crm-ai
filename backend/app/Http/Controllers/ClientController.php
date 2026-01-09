<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    // ===== SANTÉ / SOUHAIT =====

    public function storeSanteSouhait(Request $request, Client $client): JsonResponse
    {
        $this->authorize('update', $client);

        $validated = $request->validate([
            'contrat_en_place' => 'nullable|string|max:255',
            'budget_mensuel_maximum' => 'nullable|numeric|min:0',
            'niveau_hospitalisation' => 'nullable|integer|min:0|max:10',
            'niveau_chambre_particuliere' => 'nullable|integer|min:0|max:10',
            'niveau_medecin_generaliste' => 'nullable|integer|min:0|max:10',
            'niveau_analyses_imagerie' => 'nullable|integer|min:0|max:10',
            'niveau_auxiliaires_medicaux' => 'nullable|integer|min:0|max:10',
            'niveau_pharmacie' => 'nullable|integer|min:0|max:10',
            'niveau_dentaire' => 'nullable|integer|min:0|max:10',
            'niveau_optique' => 'nullable|integer|min:0|max:10',
            'niveau_protheses_auditives' => 'nullable|integer|min:0|max:10',
        ]);

        $validated['client_id'] = $client->id;

        $santeSouhait = \App\Models\SanteSouhait::create($validated);

        return response()->json($santeSouhait, 201);
    }

    public function updateSanteSouhait(Request $request, Client $client): JsonResponse
    {
        $this->authorize('update', $client);

        $validated = $request->validate([
            'contrat_en_place' => 'nullable|string|max:255',
            'budget_mensuel_maximum' => 'nullable|numeric|min:0',
            'niveau_hospitalisation' => 'nullable|integer|min:0|max:10',
            'niveau_chambre_particuliere' => 'nullable|integer|min:0|max:10',
            'niveau_medecin_generaliste' => 'nullable|integer|min:0|max:10',
            'niveau_analyses_imagerie' => 'nullable|integer|min:0|max:10',
            'niveau_auxiliaires_medicaux' => 'nullable|integer|min:0|max:10',
            'niveau_pharmacie' => 'nullable|integer|min:0|max:10',
            'niveau_dentaire' => 'nullable|integer|min:0|max:10',
            'niveau_optique' => 'nullable|integer|min:0|max:10',
            'niveau_protheses_auditives' => 'nullable|integer|min:0|max:10',
        ]);

        $santeSouhait = $client->santeSouhait;

        if ($santeSouhait) {
            $santeSouhait->update($validated);
        } else {
            $validated['client_id'] = $client->id;
            $santeSouhait = \App\Models\SanteSouhait::create($validated);
        }

        return response()->json($santeSouhait);
    }

    public function deleteSanteSouhait(Client $client): JsonResponse
    {
        $this->authorize('update', $client);

        if ($client->santeSouhait) {
            $client->santeSouhait->delete();
        }

        return response()->json(null, 204);
    }

    // ===== BAE PRÉVOYANCE =====

    public function storeBaePrevoyance(Request $request, Client $client): JsonResponse
    {
        $this->authorize('update', $client);

        $validated = $request->validate([
            'contrat_en_place' => 'nullable|string|max:255',
            'date_effet' => 'nullable|date',
            'cotisations' => 'nullable|numeric|min:0',
            'souhaite_couverture_invalidite' => 'nullable|boolean',
            'revenu_a_garantir' => 'nullable|numeric|min:0',
            'souhaite_couvrir_charges_professionnelles' => 'nullable|boolean',
            'montant_annuel_charges_professionnelles' => 'nullable|numeric|min:0',
            'garantir_totalite_charges_professionnelles' => 'nullable|boolean',
            'montant_charges_professionnelles_a_garantir' => 'nullable|numeric|min:0',
            'duree_indemnisation_souhaitee' => 'nullable|string|max:255',
            'capital_deces_souhaite' => 'nullable|numeric|min:0',
            'garanties_obseques' => 'nullable|string|max:255',
            'rente_enfants' => 'nullable|string|max:255',
            'rente_conjoint' => 'nullable|string|max:255',
            'payeur' => 'nullable|string|max:255',
        ]);

        $validated['client_id'] = $client->id;

        $baePrevoyance = \App\Models\BaePrevoyance::create($validated);

        return response()->json($baePrevoyance, 201);
    }

    public function updateBaePrevoyance(Request $request, Client $client): JsonResponse
    {
        $this->authorize('update', $client);

        $validated = $request->validate([
            'contrat_en_place' => 'nullable|string|max:255',
            'date_effet' => 'nullable|date',
            'cotisations' => 'nullable|numeric|min:0',
            'souhaite_couverture_invalidite' => 'nullable|boolean',
            'revenu_a_garantir' => 'nullable|numeric|min:0',
            'souhaite_couvrir_charges_professionnelles' => 'nullable|boolean',
            'montant_annuel_charges_professionnelles' => 'nullable|numeric|min:0',
            'garantir_totalite_charges_professionnelles' => 'nullable|boolean',
            'montant_charges_professionnelles_a_garantir' => 'nullable|numeric|min:0',
            'duree_indemnisation_souhaitee' => 'nullable|string|max:255',
            'capital_deces_souhaite' => 'nullable|numeric|min:0',
            'garanties_obseques' => 'nullable|string|max:255',
            'rente_enfants' => 'nullable|string|max:255',
            'rente_conjoint' => 'nullable|string|max:255',
            'payeur' => 'nullable|string|max:255',
        ]);

        $baePrevoyance = $client->baePrevoyance;

        if ($baePrevoyance) {
            $baePrevoyance->update($validated);
        } else {
            $validated['client_id'] = $client->id;
            $baePrevoyance = \App\Models\BaePrevoyance::create($validated);
        }

        return response()->json($baePrevoyance);
    }

    // ===== BAE RETRAITE =====

    public function storeBaeRetraite(Request $request, Client $client): JsonResponse
    {
        $this->authorize('update', $client);

        $validated = $request->validate([
            'revenus_annuels' => 'nullable|numeric|min:0',
            'revenus_annuels_foyer' => 'nullable|numeric|min:0',
            'impot_revenu' => 'nullable|numeric|min:0',
            'nombre_parts_fiscales' => 'nullable|numeric|min:0',
            'tmi' => 'nullable|string|max:50',
            'impot_paye_n_1' => 'nullable|numeric|min:0',
            'age_depart_retraite' => 'nullable|integer|min:0|max:100',
            'age_depart_retraite_conjoint' => 'nullable|integer|min:0|max:100',
            'pourcentage_revenu_a_maintenir' => 'nullable|numeric|min:0|max:100',
            'contrat_en_place' => 'nullable|string|max:255',
            'bilan_retraite_disponible' => 'nullable|boolean',
            'complementaire_retraite_mise_en_place' => 'nullable|boolean',
            'designation_etablissement' => 'nullable|string|max:255',
            'cotisations_annuelles' => 'nullable|numeric|min:0',
            'titulaire' => 'nullable|string|max:255',
        ]);

        $validated['client_id'] = $client->id;

        $baeRetraite = \App\Models\BaeRetraite::create($validated);

        return response()->json($baeRetraite, 201);
    }

    public function updateBaeRetraite(Request $request, Client $client): JsonResponse
    {
        $this->authorize('update', $client);

        $validated = $request->validate([
            'revenus_annuels' => 'nullable|numeric|min:0',
            'revenus_annuels_foyer' => 'nullable|numeric|min:0',
            'impot_revenu' => 'nullable|numeric|min:0',
            'nombre_parts_fiscales' => 'nullable|numeric|min:0',
            'tmi' => 'nullable|string|max:50',
            'impot_paye_n_1' => 'nullable|numeric|min:0',
            'age_depart_retraite' => 'nullable|integer|min:0|max:100',
            'age_depart_retraite_conjoint' => 'nullable|integer|min:0|max:100',
            'pourcentage_revenu_a_maintenir' => 'nullable|numeric|min:0|max:100',
            'contrat_en_place' => 'nullable|string|max:255',
            'bilan_retraite_disponible' => 'nullable|boolean',
            'complementaire_retraite_mise_en_place' => 'nullable|boolean',
            'designation_etablissement' => 'nullable|string|max:255',
            'cotisations_annuelles' => 'nullable|numeric|min:0',
            'titulaire' => 'nullable|string|max:255',
        ]);

        $baeRetraite = $client->baeRetraite;

        if ($baeRetraite) {
            $baeRetraite->update($validated);
        } else {
            $validated['client_id'] = $client->id;
            $baeRetraite = \App\Models\BaeRetraite::create($validated);
        }

        return response()->json($baeRetraite);
    }

    // ===== BAE ÉPARGNE =====

    public function storeBaeEpargne(Request $request, Client $client): JsonResponse
    {
        $this->authorize('update', $client);

        $detailFields = [
            'actifs_financiers_details',
            'actifs_immo_details',
            'actifs_autres_details',
            'passifs_details',
            'charges_details',
        ];
        foreach ($detailFields as $field) {
            $value = $request->input($field);
            if (is_string($value)) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $request->merge([$field => $decoded]);
                }
            }
        }

        $validated = $request->validate([
            'epargne_disponible' => 'nullable|boolean',
            'montant_epargne_disponible' => 'nullable|numeric|min:0',
            'donation_realisee' => 'nullable|boolean',
            'donation_forme' => 'nullable|string|max:255',
            'donation_date' => 'nullable|date',
            'donation_montant' => 'nullable|numeric|min:0',
            'donation_beneficiaires' => 'nullable|string',
            'capacite_epargne_estimee' => 'nullable|numeric|min:0',
            'actifs_financiers_pourcentage' => 'nullable|numeric|min:0|max:100',
            'actifs_financiers_total' => 'nullable|numeric|min:0',
            'actifs_financiers_details' => 'nullable|array',
            'actifs_immo_pourcentage' => 'nullable|numeric|min:0|max:100',
            'actifs_immo_total' => 'nullable|numeric|min:0',
            'actifs_immo_details' => 'nullable|array',
            'actifs_autres_pourcentage' => 'nullable|numeric|min:0|max:100',
            'actifs_autres_total' => 'nullable|numeric|min:0',
            'actifs_autres_details' => 'nullable|array',
            'passifs_total_emprunts' => 'nullable|numeric|min:0',
            'passifs_details' => 'nullable|array',
            'charges_totales' => 'nullable|numeric|min:0',
            'charges_details' => 'nullable|array',
            'situation_financiere_revenus_charges' => 'nullable|string',
        ]);

        $validated['client_id'] = $client->id;

        $baeEpargne = \App\Models\BaeEpargne::create($validated);

        return response()->json($baeEpargne, 201);
    }

    public function updateBaeEpargne(Request $request, Client $client): JsonResponse
    {
        $this->authorize('update', $client);

        $detailFields = [
            'actifs_financiers_details',
            'actifs_immo_details',
            'actifs_autres_details',
            'passifs_details',
            'charges_details',
        ];
        foreach ($detailFields as $field) {
            $value = $request->input($field);
            if (is_string($value)) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $request->merge([$field => $decoded]);
                }
            }
        }

        $validated = $request->validate([
            'epargne_disponible' => 'nullable|boolean',
            'montant_epargne_disponible' => 'nullable|numeric|min:0',
            'donation_realisee' => 'nullable|boolean',
            'donation_forme' => 'nullable|string|max:255',
            'donation_date' => 'nullable|date',
            'donation_montant' => 'nullable|numeric|min:0',
            'donation_beneficiaires' => 'nullable|string',
            'capacite_epargne_estimee' => 'nullable|numeric|min:0',
            'actifs_financiers_pourcentage' => 'nullable|numeric|min:0|max:100',
            'actifs_financiers_total' => 'nullable|numeric|min:0',
            'actifs_financiers_details' => 'nullable|array',
            'actifs_immo_pourcentage' => 'nullable|numeric|min:0|max:100',
            'actifs_immo_total' => 'nullable|numeric|min:0',
            'actifs_immo_details' => 'nullable|array',
            'actifs_autres_pourcentage' => 'nullable|numeric|min:0|max:100',
            'actifs_autres_total' => 'nullable|numeric|min:0',
            'actifs_autres_details' => 'nullable|array',
            'passifs_total_emprunts' => 'nullable|numeric|min:0',
            'passifs_details' => 'nullable|array',
            'charges_totales' => 'nullable|numeric|min:0',
            'charges_details' => 'nullable|array',
            'situation_financiere_revenus_charges' => 'nullable|string',
        ]);

        $baeEpargne = $client->baeEpargne;

        if ($baeEpargne) {
            $baeEpargne->update($validated);
        } else {
            $validated['client_id'] = $client->id;
            $baeEpargne = \App\Models\BaeEpargne::create($validated);
        }

        return response()->json($baeEpargne);
    }

    public function deleteBaePrevoyance(Client $client): JsonResponse
    {
        $this->authorize('update', $client);

        if ($client->baePrevoyance) {
            $client->baePrevoyance->delete();
        }

        return response()->json(null, 204);
    }

    public function deleteBaeRetraite(Client $client): JsonResponse
    {
        $this->authorize('update', $client);

        if ($client->baeRetraite) {
            $client->baeRetraite->delete();
        }

        return response()->json(null, 204);
    }

    public function deleteBaeEpargne(Client $client): JsonResponse
    {
        $this->authorize('update', $client);

        if ($client->baeEpargne) {
            $client->baeEpargne->delete();
        }

        return response()->json(null, 204);
    }

    // ===== CONJOINT =====

    public function storeConjoint(Request $request, Client $client): JsonResponse
    {
        $this->authorize('update', $client);

        // Convertir les chaînes vides en null pour les dates
        $data = $request->all();
        foreach (['date_naissance', 'date_evenement_professionnel'] as $dateField) {
            if (isset($data[$dateField]) && $data[$dateField] === '') {
                $data[$dateField] = null;
            }
        }
        $request->merge($data);

        $validated = $request->validate([
            'nom' => 'nullable|string|max:255',
            'nom_jeune_fille' => 'nullable|string|max:255',
            'prenom' => 'nullable|string|max:255',
            'date_naissance' => 'nullable|date',
            'lieu_naissance' => 'nullable|string|max:255',
            'nationalite' => 'nullable|string|max:255',
            'profession' => 'nullable|string|max:255',
            'situation_professionnelle' => 'nullable|string|max:255',
            'situation_chomage' => 'nullable|string|max:255',
            'statut' => 'nullable|string|max:255',
            'chef_entreprise' => 'nullable|boolean',
            'travailleur_independant' => 'nullable|boolean',
            'situation_actuelle_statut' => 'nullable|string|max:255',
            'niveau_activite_sportive' => 'nullable|string|max:255',
            'details_activites_sportives' => 'nullable|string',
            'date_evenement_professionnel' => 'nullable|date',
            'risques_professionnels' => 'nullable|boolean',
            'details_risques_professionnels' => 'nullable|string',
            'telephone' => 'nullable|string|max:50',
            'adresse' => 'nullable|string|max:500',
            'code_postal' => 'nullable|string|max:20',
            'ville' => 'nullable|string|max:255',
            'fumeur' => 'nullable|boolean',
            'km_parcourus_annuels' => 'nullable|integer|min:0',
        ]);

        $validated['client_id'] = $client->id;

        $conjoint = \App\Models\Conjoint::create($validated);

        return response()->json($conjoint, 201);
    }

    public function updateConjoint(Request $request, Client $client): JsonResponse
    {
        $this->authorize('update', $client);

        // Convertir les chaînes vides en null pour les dates
        $data = $request->all();
        foreach (['date_naissance', 'date_evenement_professionnel'] as $dateField) {
            if (isset($data[$dateField]) && $data[$dateField] === '') {
                $data[$dateField] = null;
            }
        }
        $request->merge($data);

        $validated = $request->validate([
            'nom' => 'nullable|string|max:255',
            'nom_jeune_fille' => 'nullable|string|max:255',
            'prenom' => 'nullable|string|max:255',
            'date_naissance' => 'nullable|date',
            'lieu_naissance' => 'nullable|string|max:255',
            'nationalite' => 'nullable|string|max:255',
            'profession' => 'nullable|string|max:255',
            'situation_professionnelle' => 'nullable|string|max:255',
            'situation_chomage' => 'nullable|string|max:255',
            'statut' => 'nullable|string|max:255',
            'chef_entreprise' => 'nullable|boolean',
            'travailleur_independant' => 'nullable|boolean',
            'situation_actuelle_statut' => 'nullable|string|max:255',
            'niveau_activite_sportive' => 'nullable|string|max:255',
            'details_activites_sportives' => 'nullable|string',
            'date_evenement_professionnel' => 'nullable|date',
            'risques_professionnels' => 'nullable|boolean',
            'details_risques_professionnels' => 'nullable|string',
            'telephone' => 'nullable|string|max:50',
            'adresse' => 'nullable|string|max:500',
            'code_postal' => 'nullable|string|max:20',
            'ville' => 'nullable|string|max:255',
            'fumeur' => 'nullable|boolean',
            'km_parcourus_annuels' => 'nullable|integer|min:0',
        ]);

        $conjoint = $client->conjoint;

        if ($conjoint) {
            $conjoint->update($validated);
        } else {
            $validated['client_id'] = $client->id;
            $conjoint = \App\Models\Conjoint::create($validated);
        }

        return response()->json($conjoint);
    }

    public function deleteConjoint(Client $client): JsonResponse
    {
        $this->authorize('update', $client);

        if ($client->conjoint) {
            $client->conjoint->delete();
        }

        return response()->json(null, 204);
    }

    // ===== ENFANTS =====

    public function storeEnfant(Request $request, Client $client): JsonResponse
    {
        $this->authorize('update', $client);

        // Convertir les chaînes vides en null pour les dates
        $data = $request->all();
        if (isset($data['date_naissance']) && $data['date_naissance'] === '') {
            $data['date_naissance'] = null;
        }
        $request->merge($data);

        $validated = $request->validate([
            'nom' => 'nullable|string|max:255',
            'prenom' => 'nullable|string|max:255',
            'date_naissance' => 'nullable|date',
            'fiscalement_a_charge' => 'nullable|boolean',
            'garde_alternee' => 'nullable|boolean',
        ]);

        $validated['client_id'] = $client->id;

        $enfant = \App\Models\Enfant::create($validated);

        return response()->json($enfant, 201);
    }

    public function updateEnfant(Request $request, Client $client, \App\Models\Enfant $enfant): JsonResponse
    {
        $this->authorize('update', $client);

        // Vérifier que l'enfant appartient bien au client
        if ($enfant->client_id !== $client->id) {
            return response()->json(['error' => 'Enfant non trouvé'], 404);
        }

        // Convertir les chaînes vides en null pour les dates
        $data = $request->all();
        if (isset($data['date_naissance']) && $data['date_naissance'] === '') {
            $data['date_naissance'] = null;
        }
        $request->merge($data);

        $validated = $request->validate([
            'nom' => 'nullable|string|max:255',
            'prenom' => 'nullable|string|max:255',
            'date_naissance' => 'nullable|date',
            'fiscalement_a_charge' => 'nullable|boolean',
            'garde_alternee' => 'nullable|boolean',
        ]);

        $enfant->update($validated);

        return response()->json($enfant);
    }

    public function deleteEnfant(Client $client, \App\Models\Enfant $enfant): JsonResponse
    {
        $this->authorize('update', $client);

        // Vérifier que l'enfant appartient bien au client
        if ($enfant->client_id !== $client->id) {
            return response()->json(['error' => 'Enfant non trouvé'], 404);
        }

        $enfant->delete();

        return response()->json(null, 204);
    }
}
