<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientPendingChange;
use App\Services\MergeService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PendingChangesController extends Controller
{
    private MergeService $mergeService;

    public function __construct(MergeService $mergeService)
    {
        $this->mergeService = $mergeService;
    }

    /**
     * Liste tous les pending changes pour l'utilisateur connecté
     */
    public function index(Request $request): JsonResponse
    {
        $pendingChanges = ClientPendingChange::forUser(auth()->id())
            ->whereIn('status', ['pending', 'reviewing'])
            ->with(['client:id,nom,prenom,email', 'audioRecord:id,path,created_at'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($pc) {
                return [
                    'id' => $pc->id,
                    'client' => $pc->client ? [
                        'id' => $pc->client->id,
                        'nom' => $pc->client->nom,
                        'prenom' => $pc->client->prenom,
                        'email' => $pc->client->email,
                        'full_name' => trim(($pc->client->prenom ?? '') . ' ' . ($pc->client->nom ?? '')),
                    ] : null,
                    'source' => $pc->source,
                    'status' => $pc->status,
                    'changes_count' => $pc->changes_count,
                    'conflicts_count' => $pc->conflicts_count,
                    'created_at' => $pc->created_at->toIso8601String(),
                    'audio_record_id' => $pc->audio_record_id,
                ];
            });

        return response()->json([
            'pending_changes' => $pendingChanges,
            'total_count' => $pendingChanges->count(),
            'conflicts_total' => $pendingChanges->sum('conflicts_count'),
        ]);
    }

    /**
     * Affiche le détail d'un pending change avec le diff complet
     */
    public function show(ClientPendingChange $pendingChange): JsonResponse
    {
        // Vérifier que l'utilisateur a accès
        $this->authorize('view', $pendingChange);

        // Charger les relations
        $pendingChange->load(['client', 'audioRecord', 'reviewer']);

        return response()->json([
            'id' => $pendingChange->id,
            'client' => $pendingChange->client ? [
                'id' => $pendingChange->client->id,
                'nom' => $pendingChange->client->nom,
                'prenom' => $pendingChange->client->prenom,
                'email' => $pendingChange->client->email,
                'full_name' => trim(($pendingChange->client->prenom ?? '') . ' ' . ($pendingChange->client->nom ?? '')),
            ] : null,
            'source' => $pendingChange->source,
            'status' => $pendingChange->status,
            'changes_diff' => $pendingChange->changes_diff,
            'actual_changes' => $pendingChange->actual_changes,
            'changes_count' => $pendingChange->changes_count,
            'conflicts_count' => $pendingChange->conflicts_count,
            'extracted_data' => $pendingChange->extracted_data,
            'user_decisions' => $pendingChange->user_decisions,
            'notes' => $pendingChange->notes,
            'audio_record_id' => $pendingChange->audio_record_id,
            'created_at' => $pendingChange->created_at->toIso8601String(),
            'reviewed_at' => $pendingChange->reviewed_at?->toIso8601String(),
            'reviewed_by' => $pendingChange->reviewer ? [
                'id' => $pendingChange->reviewer->id,
                'name' => $pendingChange->reviewer->name,
            ] : null,
        ]);
    }

    /**
     * Applique les changements sélectionnés
     */
    public function apply(Request $request, ClientPendingChange $pendingChange): JsonResponse
    {
        // Vérifier que l'utilisateur a accès
        $this->authorize('update', $pendingChange);

        // Vérifier le statut
        if (!in_array($pendingChange->status, ['pending', 'reviewing'])) {
            return response()->json([
                'error' => 'Ce changement a déjà été traité',
                'status' => $pendingChange->status,
            ], 422);
        }

        $request->validate([
            'decisions' => 'required|array',
            'decisions.*' => 'in:accept,reject,skip',
        ]);

        try {
            $result = $this->mergeService->applyChanges(
                $pendingChange,
                $request->input('decisions'),
                auth()->id()
            );

            Log::info("✅ [PENDING CHANGES] Changements appliqués", [
                'pending_change_id' => $pendingChange->id,
                'applied_count' => count($result['applied']),
                'rejected_count' => count($result['rejected']),
            ]);

            return response()->json([
                'success' => true,
                'message' => sprintf(
                    '%d champ(s) mis à jour, %d rejeté(s)',
                    count($result['applied']),
                    count($result['rejected'])
                ),
                'applied' => $result['applied'],
                'rejected' => $result['rejected'],
                'client' => $result['client'],
            ]);

        } catch (\Throwable $e) {
            Log::error("❌ [PENDING CHANGES] Erreur lors de l'application", [
                'pending_change_id' => $pendingChange->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Erreur lors de l\'application des changements',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Accepte tous les changements
     */
    public function acceptAll(ClientPendingChange $pendingChange): JsonResponse
    {
        $this->authorize('update', $pendingChange);

        if (!in_array($pendingChange->status, ['pending', 'reviewing'])) {
            return response()->json([
                'error' => 'Ce changement a déjà été traité',
            ], 422);
        }

        // Créer des décisions "accept" pour tous les changements
        $decisions = [];
        foreach ($pendingChange->changes_diff as $field => $change) {
            if ($change['has_change'] ?? false) {
                $decisions[$field] = 'accept';
            }
        }

        try {
            $result = $this->mergeService->applyChanges($pendingChange, $decisions, auth()->id());

            return response()->json([
                'success' => true,
                'message' => 'Tous les changements ont été appliqués',
                'applied' => $result['applied'],
                'client' => $result['client'],
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Erreur lors de l\'application',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Rejette tous les changements
     */
    public function rejectAll(Request $request, ClientPendingChange $pendingChange): JsonResponse
    {
        $this->authorize('update', $pendingChange);

        if (!in_array($pendingChange->status, ['pending', 'reviewing'])) {
            return response()->json([
                'error' => 'Ce changement a déjà été traité',
            ], 422);
        }

        $reason = $request->input('reason');

        $this->mergeService->rejectAll($pendingChange, auth()->id(), $reason);

        return response()->json([
            'success' => true,
            'message' => 'Tous les changements ont été rejetés',
        ]);
    }

    /**
     * Applique automatiquement les changements "sûrs" (sans conflit)
     */
    public function autoApplySafe(ClientPendingChange $pendingChange): JsonResponse
    {
        $this->authorize('update', $pendingChange);

        if (!in_array($pendingChange->status, ['pending', 'reviewing'])) {
            return response()->json([
                'error' => 'Ce changement a déjà été traité',
            ], 422);
        }

        try {
            $result = $this->mergeService->autoApplySafeChanges($pendingChange, auth()->id());

            return response()->json([
                'success' => true,
                'message' => sprintf(
                    '%d champ(s) appliqués automatiquement (sans conflit)',
                    count($result['applied'])
                ),
                'applied' => $result['applied'],
                'remaining_conflicts' => $result['rejected'],
                'client' => $result['client'],
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Erreur lors de l\'application automatique',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Compte les pending changes pour un client
     */
    public function countForClient(Client $client): JsonResponse
    {
        $count = ClientPendingChange::forClient($client->id)
            ->whereIn('status', ['pending', 'reviewing'])
            ->count();

        return response()->json([
            'client_id' => $client->id,
            'pending_count' => $count,
        ]);
    }

    /**
     * Liste les pending changes pour un client spécifique
     */
    public function forClient(Client $client): JsonResponse
    {
        $pendingChanges = ClientPendingChange::forClient($client->id)
            ->whereIn('status', ['pending', 'reviewing'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($pc) {
                return [
                    'id' => $pc->id,
                    'source' => $pc->source,
                    'status' => $pc->status,
                    'changes_count' => $pc->changes_count,
                    'conflicts_count' => $pc->conflicts_count,
                    'created_at' => $pc->created_at->toIso8601String(),
                ];
            });

        return response()->json([
            'client_id' => $client->id,
            'pending_changes' => $pendingChanges,
        ]);
    }
}
