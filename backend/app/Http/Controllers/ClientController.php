<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\HasClientSubresources;
use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use App\Models\ClientContrat;
use App\Services\BesoinService;
use App\Services\Import\ImportDuplicateDetectionService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClientController extends Controller {
    use HasClientSubresources;

    public function __construct(private readonly BesoinService $besoinService) {}

    // =========================================================================
    // CRUD CLIENT
    // =========================================================================

    public function index(Request $request): AnonymousResourceCollection {
        $user = auth()->user();
        $team = $user->currentTeam();

        $query = Client::with([
            'conjoint', 'enfants', 'contrats.assureur',
            'baePrevoyance', 'baeRetraite', 'baeEpargne', 'santeSouhait',
        ]);

        $isAdmin = $team && $user->isTeamAdmin($team);
        if (! $isAdmin && ! $user->isSuperAdmin()) {
            $query->where('user_id', $user->id);
        }

        $type = $request->get('type', 'active');
        match ($type) {
            'prospects' => $query->prospects(),
            'clients' => $query->clients(),
            'archived' => $query->archived(),
            'active' => $query->active(),
            'all' => null,
            default => $query->active(),
        };

        return ClientResource::collection($query->latest('id')->get());
    }

    public function show(int $id): ClientResource {
        $user = auth()->user();
        $team = $user->currentTeam();

        $client = Client::with([
            'conjoint', 'enfants', 'santeSouhait',
            'baePrevoyance', 'baeRetraite', 'baeEpargne',
            'revenus', 'passifs', 'actifsFinanciers',
            'biensImmobiliers', 'autresEpargnes', 'charges',
            'contrats.assureur',
        ])->findOrFail($id);

        $isAdmin = $team && $user->isTeamAdmin($team);
        if (! $isAdmin && ! $user->isSuperAdmin()) {
            abort_if($client->user_id !== $user->id, 403, 'Accès refusé à ce client.');
        }

        return ClientResource::make($client);
    }

    public function store(StoreClientRequest $request): JsonResponse {
        $this->authorize('create', Client::class);

        $teamId = auth()->user()->currentTeam()?->id;

        try {
            $client = Client::create(array_merge($request->validated(), [
                'user_id' => auth()->id(),
                'team_id' => $teamId,
            ]));

            return ClientResource::make($client)->response()->setStatusCode(201);
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                $existing = Client::where('team_id', $teamId)
                    ->where('email', $request->input('email'))
                    ->first();

                return response()->json([
                    'message' => 'Un client avec cet email existe déjà dans votre cabinet.',
                    'existing_client' => $existing
                        ? ['id' => $existing->id, 'nom' => $existing->nom, 'prenom' => $existing->prenom]
                        : null,
                ], 409);
            }
            throw $e;
        }
    }

    public function checkDuplicate(Request $request): JsonResponse {
        $request->validate([
            'email' => 'nullable|email',
            'telephone' => 'nullable|string',
            'nom' => 'nullable|string',
            'prenom' => 'nullable|string',
        ]);

        $teamId = auth()->user()->currentTeam()?->id;
        $result = app(ImportDuplicateDetectionService::class)
            ->findDuplicates($request->only(['email', 'telephone', 'nom', 'prenom']), $teamId);

        // Hydrate best_match with client info for the frontend
        if (! empty($result['best_match'])) {
            $client = Client::find($result['best_match']['client_id']);
            if ($client) {
                $result['best_match'] = array_merge($result['best_match'], [
                    'id' => $client->id,
                    'nom' => $client->nom,
                    'prenom' => $client->prenom,
                ]);
            }
        }

        return response()->json($result);
    }

    public function update(UpdateClientRequest $request, int $id): ClientResource {
        $client = Client::findOrFail($id);
        $this->authorize('update', $client);

        $validated = $request->validated();

        if (isset($validated['besoins']) && is_array($validated['besoins'])) {
            $validated['besoins'] = $this->besoinService->normalizeSlugs($validated['besoins']);
        }

        $client->update($validated);

        if (isset($validated['besoins'])) {
            $this->besoinService->createBaeSectionsFromBesoins($client, $validated['besoins']);
        }

        $this->besoinService->syncBesoinsFromBae($client);

        return ClientResource::make($client->fresh([
            'conjoint', 'enfants', 'santeSouhait',
            'baePrevoyance', 'baeRetraite', 'baeEpargne',
            'revenus', 'passifs', 'actifsFinanciers',
            'biensImmobiliers', 'autresEpargnes',
        ]));
    }

    public function destroy(int $id): JsonResponse {
        $client = Client::findOrFail($id);
        $this->authorize('delete', $client);
        $client->delete();

        return response()->json(null, 204);
    }

    public function updateStatus(Request $request, Client $client): JsonResponse {
        $this->authorize('update', $client);
        $validated = $request->validate(['is_client' => 'required|boolean']);
        $client->update(['is_client' => $validated['is_client']]);

        return response()->json([
            'message' => $validated['is_client']
                ? 'Le prospect a été converti en client.'
                : 'Le client a été rétrogradé en prospect.',
            'client' => ClientResource::make($client->fresh()),
        ]);
    }

    public function archive(Client $client): JsonResponse {
        $this->authorize('update', $client);
        $client->update(['is_archived' => true]);

        return response()->json(['message' => 'Le contact a été archivé.', 'client' => ClientResource::make($client->fresh())]);
    }

    public function restore(Client $client): JsonResponse {
        $this->authorize('update', $client);
        $client->update(['is_archived' => false]);

        return response()->json(['message' => 'Le contact a été restauré.', 'client' => ClientResource::make($client->fresh())]);
    }

    // =========================================================================
    // SOUS-RESSOURCES FINANCIÈRES — via trait HasClientSubresources
    // =========================================================================

    private const REVENU_RULES = [
        'nature' => 'nullable|string|max:255',
        'periodicite' => 'nullable|string|max:50',
        'montant' => 'nullable|numeric|min:0',
    ];

    public function storeRevenu(Request $request, Client $client): JsonResponse {
        return $this->storeSubresource($request, $client, 'revenus', self::REVENU_RULES);
    }

    public function updateRevenu(Request $request, Client $client, int $revenu): JsonResponse {
        return $this->updateSubresource($request, $client, 'revenus', $revenu, self::REVENU_RULES);
    }

    public function deleteRevenu(Client $client, int $revenu): JsonResponse {
        return $this->deleteSubresource($client, 'revenus', $revenu);
    }

    private const PASSIF_RULES = [
        'nature' => 'nullable|string|max:255',
        'preteur' => 'nullable|string|max:255',
        'periodicite' => 'nullable|string|max:50',
        'montant_remboursement' => 'nullable|numeric|min:0',
        'capital_restant_du' => 'nullable|numeric|min:0',
        'duree_restante' => 'nullable|integer|min:0',
    ];

    public function storePassif(Request $request, Client $client): JsonResponse {
        return $this->storeSubresource($request, $client, 'passifs', self::PASSIF_RULES);
    }

    public function updatePassif(Request $request, Client $client, int $passif): JsonResponse {
        return $this->updateSubresource($request, $client, 'passifs', $passif, self::PASSIF_RULES);
    }

    public function deletePassif(Client $client, int $passif): JsonResponse {
        return $this->deleteSubresource($client, 'passifs', $passif);
    }

    private const ACTIF_RULES = [
        'nature' => 'nullable|string|max:255',
        'etablissement' => 'nullable|string|max:255',
        'detenteur' => 'nullable|string|max:255',
        'date_ouverture_souscription' => 'nullable|date',
        'valeur_actuelle' => 'nullable|numeric|min:0',
    ];

    public function storeActifFinancier(Request $request, Client $client): JsonResponse {
        return $this->storeSubresource($request, $client, 'actifsFinanciers', self::ACTIF_RULES);
    }

    public function updateActifFinancier(Request $request, Client $client, int $actifFinancier): JsonResponse {
        return $this->updateSubresource($request, $client, 'actifsFinanciers', $actifFinancier, self::ACTIF_RULES);
    }

    public function deleteActifFinancier(Client $client, int $actifFinancier): JsonResponse {
        return $this->deleteSubresource($client, 'actifsFinanciers', $actifFinancier);
    }

    private const BIEN_RULES = [
        'designation' => 'nullable|string|max:255',
        'detenteur' => 'nullable|string|max:255',
        'forme_propriete' => 'nullable|string|max:255',
        'valeur_actuelle_estimee' => 'nullable|numeric|min:0',
        'annee_acquisition' => 'nullable|integer|min:1900|max:'.PHP_INT_MAX,
        'valeur_acquisition' => 'nullable|numeric|min:0',
    ];

    public function storeBienImmobilier(Request $request, Client $client): JsonResponse {
        return $this->storeSubresource($request, $client, 'biensImmobiliers', self::BIEN_RULES);
    }

    public function updateBienImmobilier(Request $request, Client $client, int $bienImmobilier): JsonResponse {
        return $this->updateSubresource($request, $client, 'biensImmobiliers', $bienImmobilier, self::BIEN_RULES);
    }

    public function deleteBienImmobilier(Client $client, int $bienImmobilier): JsonResponse {
        return $this->deleteSubresource($client, 'biensImmobiliers', $bienImmobilier);
    }

    private const EPARGNE_RULES = [
        'designation' => 'nullable|string|max:255',
        'detenteur' => 'nullable|string|max:255',
        'valeur' => 'nullable|numeric|min:0',
    ];

    public function storeAutreEpargne(Request $request, Client $client): JsonResponse {
        return $this->storeSubresource($request, $client, 'autresEpargnes', self::EPARGNE_RULES);
    }

    public function updateAutreEpargne(Request $request, Client $client, int $autreEpargne): JsonResponse {
        return $this->updateSubresource($request, $client, 'autresEpargnes', $autreEpargne, self::EPARGNE_RULES);
    }

    public function deleteAutreEpargne(Client $client, int $autreEpargne): JsonResponse {
        return $this->deleteSubresource($client, 'autresEpargnes', $autreEpargne);
    }

    private const CHARGE_RULES = [
        'nature' => 'nullable|string|max:255',
        'periodicite' => 'nullable|string|max:255',
        'montant' => 'nullable|numeric|min:0',
    ];

    public function storeCharge(Request $request, Client $client): JsonResponse {
        return $this->storeSubresource($request, $client, 'charges', self::CHARGE_RULES);
    }

    public function updateCharge(Request $request, Client $client, int $charge): JsonResponse {
        return $this->updateSubresource($request, $client, 'charges', $charge, self::CHARGE_RULES);
    }

    public function deleteCharge(Client $client, int $charge): JsonResponse {
        return $this->deleteSubresource($client, 'charges', $charge);
    }

    // =========================================================================
    // SINGLETONS (HasOne) — santeSouhait, baePrevoyance, baeRetraite, baeEpargne, conjoint
    // =========================================================================

    private const SANTE_RULES = [
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
    ];

    public function storeSanteSouhait(Request $request, Client $client): JsonResponse {
        return $this->upsertSingleton($request, $client, 'santeSouhait', self::SANTE_RULES);
    }

    public function updateSanteSouhait(Request $request, Client $client): JsonResponse {
        return $this->upsertSingleton($request, $client, 'santeSouhait', self::SANTE_RULES);
    }

    public function deleteSanteSouhait(Client $client): JsonResponse {
        return $this->deleteSingleton($client, 'santeSouhait');
    }

    private const BAE_PREVOYANCE_RULES = [
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
    ];

    public function storeBaePrevoyance(Request $request, Client $client): JsonResponse {
        return $this->upsertSingleton($request, $client, 'baePrevoyance', self::BAE_PREVOYANCE_RULES);
    }

    public function updateBaePrevoyance(Request $request, Client $client): JsonResponse {
        return $this->upsertSingleton($request, $client, 'baePrevoyance', self::BAE_PREVOYANCE_RULES);
    }

    public function deleteBaePrevoyance(Client $client): JsonResponse {
        return $this->deleteSingleton($client, 'baePrevoyance');
    }

    private const BAE_RETRAITE_RULES = [
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
    ];

    public function storeBaeRetraite(Request $request, Client $client): JsonResponse {
        return $this->upsertSingleton($request, $client, 'baeRetraite', self::BAE_RETRAITE_RULES);
    }

    public function updateBaeRetraite(Request $request, Client $client): JsonResponse {
        return $this->upsertSingleton($request, $client, 'baeRetraite', self::BAE_RETRAITE_RULES);
    }

    public function deleteBaeRetraite(Client $client): JsonResponse {
        return $this->deleteSingleton($client, 'baeRetraite');
    }

    private const BAE_EPARGNE_RULES = [
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
    ];

    public function storeBaeEpargne(Request $request, Client $client): JsonResponse {
        $this->decodeJsonFields($request, [
            'actifs_financiers_details', 'actifs_immo_details', 'actifs_autres_details',
            'passifs_details', 'charges_details',
        ]);

        return $this->upsertSingleton($request, $client, 'baeEpargne', self::BAE_EPARGNE_RULES);
    }

    public function updateBaeEpargne(Request $request, Client $client): JsonResponse {
        $this->decodeJsonFields($request, [
            'actifs_financiers_details', 'actifs_immo_details', 'actifs_autres_details',
            'passifs_details', 'charges_details',
        ]);

        return $this->upsertSingleton($request, $client, 'baeEpargne', self::BAE_EPARGNE_RULES);
    }

    public function deleteBaeEpargne(Client $client): JsonResponse {
        return $this->deleteSingleton($client, 'baeEpargne');
    }

    private const CONJOINT_RULES = [
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
    ];

    public function storeConjoint(Request $request, Client $client): JsonResponse {
        $this->nullifyEmptyDates($request, ['date_naissance', 'date_evenement_professionnel']);

        return $this->upsertSingleton($request, $client, 'conjoint', self::CONJOINT_RULES);
    }

    public function updateConjoint(Request $request, Client $client): JsonResponse {
        $this->nullifyEmptyDates($request, ['date_naissance', 'date_evenement_professionnel']);

        return $this->upsertSingleton($request, $client, 'conjoint', self::CONJOINT_RULES);
    }

    public function deleteConjoint(Client $client): JsonResponse {
        return $this->deleteSingleton($client, 'conjoint');
    }

    // =========================================================================
    // ENFANTS
    // =========================================================================

    private const ENFANT_RULES = [
        'nom' => 'nullable|string|max:255',
        'prenom' => 'nullable|string|max:255',
        'date_naissance' => 'nullable|date',
        'fiscalement_a_charge' => 'nullable|boolean',
        'garde_alternee' => 'nullable|boolean',
    ];

    public function storeEnfant(Request $request, Client $client): JsonResponse {
        $this->nullifyEmptyDates($request, ['date_naissance']);

        return $this->storeSubresource($request, $client, 'enfants', self::ENFANT_RULES);
    }

    public function updateEnfant(Request $request, Client $client, \App\Models\Enfant $enfant): JsonResponse {
        $this->authorize('update', $client);
        abort_if($enfant->client_id !== $client->id, 404, 'Enfant non trouvé');
        $this->nullifyEmptyDates($request, ['date_naissance']);
        $enfant->update($request->validate(self::ENFANT_RULES));

        return response()->json($enfant);
    }

    public function deleteEnfant(Client $client, \App\Models\Enfant $enfant): JsonResponse {
        $this->authorize('update', $client);
        abort_if($enfant->client_id !== $client->id, 404, 'Enfant non trouvé');
        $enfant->delete();

        return response()->json(null, 204);
    }

    // =========================================================================
    // CONTRATS
    // =========================================================================

    public function storeContrat(Request $request, Client $client): JsonResponse {
        $this->authorize('update', $client);

        $validated = $request->validate([
            'type' => 'required|in:sante,prevoyance,per,assurance_vie,emprunteur,vie_entiere',
            'assureur_id' => 'nullable|exists:assureurs,id',
            'mensualite' => 'nullable|numeric|min:0',
            'en_cours' => 'nullable|numeric|min:0',
            'fond_euro' => 'nullable|numeric|min:0',
            'uc' => 'nullable|numeric|min:0',
            'versement_programme' => 'nullable|numeric|min:0',
        ]);

        // updateOrCreate : un seul contrat par type par client (contrainte métier)
        $contrat = $client->contrats()->updateOrCreate(
            ['type' => $validated['type']],
            $validated
        );
        $contrat->load('assureur');

        return response()->json($contrat, 201);
    }

    public function updateContrat(Request $request, Client $client, ClientContrat $contrat): JsonResponse {
        $this->authorize('update', $client);
        abort_if($contrat->client_id !== $client->id, 404, 'Contrat non trouvé');

        $validated = $request->validate([
            'assureur_id' => 'nullable|exists:assureurs,id',
            'mensualite' => 'nullable|numeric|min:0',
            'en_cours' => 'nullable|numeric|min:0',
            'fond_euro' => 'nullable|numeric|min:0',
            'uc' => 'nullable|numeric|min:0',
            'versement_programme' => 'nullable|numeric|min:0',
        ]);

        $contrat->update($validated);
        $contrat->load('assureur');

        return response()->json($contrat);
    }

    public function deleteContrat(Client $client, ClientContrat $contrat): JsonResponse {
        $this->authorize('update', $client);
        abort_if($contrat->client_id !== $client->id, 404, 'Contrat non trouvé');
        $contrat->delete();

        return response()->json(null, 204);
    }

    // =========================================================================
    // HELPERS PRIVÉS
    // =========================================================================

    /** Convertit les chaînes vides en null pour les champs date avant validation */
    private function nullifyEmptyDates(Request $request, array $fields): void {
        $data = $request->all();
        foreach ($fields as $field) {
            if (isset($data[$field]) && $data[$field] === '') {
                $data[$field] = null;
            }
        }
        $request->merge($data);
    }

    /** Décode les champs JSON stockés en string avant validation */
    private function decodeJsonFields(Request $request, array $fields): void {
        $data = $request->all();
        foreach ($fields as $field) {
            $value = $data[$field] ?? null;
            if (is_string($value)) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $data[$field] = $decoded;
                }
            }
        }
        $request->merge($data);
    }
}
