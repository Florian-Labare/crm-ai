<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientComplianceDocument;
use App\Models\ComplianceRequirement;

/**
 * Calcule le statut de conformité d'un client.
 *
 * Extrait de ClientComplianceController pour rendre la logique métier
 * testable en isolation et réutilisable (badge, status, dashboard).
 */
class ComplianceStatusService
{
    public function __construct(
        private readonly BesoinService $besoinService
    ) {}

    /**
     * Calcule le badge feu tricolore pour un client.
     *
     * @return array{color: string, label: string, score: int, valid_count: int,
     *               pending_count: int, missing_count: int, total_required: int,
     *               missing_documents: string[], expired_count: int, expiring_soon_count: int}
     */
    public function computeBadge(Client $client): array
    {
        $besoins = $this->besoinService->getEffectiveBesoins($client);

        $requirements = ComplianceRequirement::getRequirementsForBesoins($besoins)
            ->where('is_mandatory', true)
            ->pluck('document_type')
            ->toArray();

        $provided = $client->complianceDocuments()
            ->whereIn('document_type', $requirements)
            ->get(['document_type', 'status', 'created_at']);

        $latestByType = $provided->sortByDesc('created_at')->keyBy('document_type');
        $validDocuments = $latestByType->where('status', 'validated')->pluck('document_type')->toArray();
        $pendingDocuments = $latestByType->where('status', 'pending')->pluck('document_type')->toArray();

        $totalRequired = count($requirements);
        $validCount = count(array_intersect($requirements, $validDocuments));
        $pendingCount = count(array_intersect($requirements, array_diff($pendingDocuments, $validDocuments)));
        $missingCount = max(0, $totalRequired - $validCount - $pendingCount);

        [$color, $label] = $this->resolveTrafficLight($totalRequired, $validCount, $pendingCount, $missingCount);

        $allProvided = array_unique(array_merge($validDocuments, $pendingDocuments));
        $missing = array_diff($requirements, $allProvided);
        $missingLabels = array_values(array_map(
            fn ($type) => ClientComplianceDocument::DOCUMENT_LABELS[$type] ?? $type,
            $missing
        ));

        return [
            'color' => $color,
            'label' => $label,
            'score' => $totalRequired > 0 ? round(($validCount / $totalRequired) * 100) : 0,
            'valid_count' => $validCount,
            'pending_count' => $pendingCount,
            'missing_count' => $missingCount,
            'total_required' => $totalRequired,
            'missing_documents' => $missingLabels,
            'expired_count' => $client->complianceDocuments()->expired()->where('status', 'validated')->count(),
            'expiring_soon_count' => $client->complianceDocuments()->expiringSoon(90)->where('status', 'validated')->count(),
        ];
    }

    /**
     * Retourne les exigences actives pour un client (utilisé par status()).
     */
    public function getRequirements(Client $client): \Illuminate\Database\Eloquent\Collection
    {
        $besoins = $this->besoinService->getEffectiveBesoins($client);

        return ComplianceRequirement::getRequirementsForBesoins($besoins);
    }

    /**
     * Feu tricolore : [couleur, label]
     */
    private function resolveTrafficLight(int $total, int $valid, int $pending, int $missing): array
    {
        if ($total === 0 || $valid === $total) {
            return ['green',  'Complet'];
        }
        if ($pending > 0 && $missing === 0) {
            return ['orange', 'En attente'];
        }
        if ($valid > 0 || $pending > 0) {
            return ['orange', 'Partiel'];
        }

        return ['red', 'Incomplet'];
    }
}
