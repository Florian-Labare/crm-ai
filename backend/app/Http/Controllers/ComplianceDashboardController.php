<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientComplianceDocument;
use App\Models\ComplianceRequirement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComplianceDashboardController extends Controller
{
    /**
     * Retourne les statistiques globales de conformité pour tous les clients
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();

        // Récupérer les clients de l'utilisateur (via team)
        $clients = Client::where('team_id', $user->current_team_id)->get();
        $totalClients = $clients->count();

        if ($totalClients === 0) {
            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => [
                        'total_clients' => 0,
                        'fully_compliant' => 0,
                        'partially_compliant' => 0,
                        'non_compliant' => 0,
                        'with_expired_docs' => 0,
                        'with_expiring_soon' => 0,
                    ],
                    'alerts' => [],
                ],
            ]);
        }

        // Documents globaux obligatoires
        $globalRequirements = ComplianceRequirement::where('besoin', 'global')
            ->where('is_mandatory', true)
            ->pluck('document_type')
            ->toArray();

        $totalGlobalRequired = count($globalRequirements);

        $fullyCompliant = 0;
        $partiallyCompliant = 0;
        $nonCompliant = 0;
        $withExpiredDocs = 0;
        $withExpiringSoon = 0;

        $alerts = [];

        foreach ($clients as $client) {
            // Compter les documents validés pour les exigences globales
            $validDocs = $client->complianceDocuments()
                ->where('status', 'validated')
                ->whereIn('document_type', $globalRequirements)
                ->whereRaw('(expires_at IS NULL OR expires_at > ?)', [now()])
                ->pluck('document_type')
                ->unique()
                ->count();

            // Catégoriser le client
            if ($validDocs === $totalGlobalRequired) {
                $fullyCompliant++;
            } elseif ($validDocs > 0) {
                $partiallyCompliant++;
            } else {
                $nonCompliant++;
            }

            // Documents expirés
            $expiredDocs = $client->complianceDocuments()
                ->expired()
                ->where('status', 'validated')
                ->get();

            if ($expiredDocs->count() > 0) {
                $withExpiredDocs++;

                foreach ($expiredDocs as $doc) {
                    $alerts[] = [
                        'client_id' => $client->id,
                        'client_name' => trim("{$client->prenom} {$client->nom}"),
                        'document_type' => $doc->document_type,
                        'document_label' => $doc->document_label,
                        'issue' => 'expired',
                        'severity' => 'high',
                        'expires_at' => $doc->expires_at?->format('Y-m-d'),
                        'days_overdue' => abs($doc->days_until_expiration),
                    ];
                }
            }

            // Documents expirant bientôt
            $expiringSoonDocs = $client->complianceDocuments()
                ->expiringSoon(90)
                ->where('status', 'validated')
                ->get();

            if ($expiringSoonDocs->count() > 0) {
                $withExpiringSoon++;

                foreach ($expiringSoonDocs as $doc) {
                    $alerts[] = [
                        'client_id' => $client->id,
                        'client_name' => trim("{$client->prenom} {$client->nom}"),
                        'document_type' => $doc->document_type,
                        'document_label' => $doc->document_label,
                        'issue' => 'expiring_soon',
                        'severity' => $doc->days_until_expiration <= 30 ? 'medium' : 'low',
                        'expires_at' => $doc->expires_at?->format('Y-m-d'),
                        'days_until_expiration' => $doc->days_until_expiration,
                    ];
                }
            }
        }

        // Trier les alertes par sévérité (high en premier, puis par date)
        usort($alerts, function ($a, $b) {
            $severityOrder = ['high' => 0, 'medium' => 1, 'low' => 2];
            $severityDiff = $severityOrder[$a['severity']] - $severityOrder[$b['severity']];

            if ($severityDiff !== 0) {
                return $severityDiff;
            }

            // À sévérité égale, trier par date d'expiration
            return strcmp($a['expires_at'] ?? '', $b['expires_at'] ?? '');
        });

        // Limiter le nombre d'alertes retournées
        $limit = $request->input('limit', 50);
        $alerts = array_slice($alerts, 0, $limit);

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'total_clients' => $totalClients,
                    'fully_compliant' => $fullyCompliant,
                    'partially_compliant' => $partiallyCompliant,
                    'non_compliant' => $nonCompliant,
                    'with_expired_docs' => $withExpiredDocs,
                    'with_expiring_soon' => $withExpiringSoon,
                ],
                'alerts' => $alerts,
            ],
        ]);
    }

    /**
     * Retourne uniquement les alertes (paginées)
     */
    public function alerts(Request $request): JsonResponse
    {
        $user = auth()->user();
        $perPage = $request->input('per_page', 20);
        $filter = $request->input('filter', 'all'); // all, expired, expiring_soon

        $alerts = [];

        // Récupérer les clients de l'utilisateur
        $clients = Client::where('team_id', $user->current_team_id)->get();

        foreach ($clients as $client) {
            // Documents expirés
            if ($filter === 'all' || $filter === 'expired') {
                $expiredDocs = $client->complianceDocuments()
                    ->expired()
                    ->where('status', 'validated')
                    ->get();

                foreach ($expiredDocs as $doc) {
                    $alerts[] = [
                        'client_id' => $client->id,
                        'client_name' => trim("{$client->prenom} {$client->nom}"),
                        'document_type' => $doc->document_type,
                        'document_label' => $doc->document_label,
                        'issue' => 'expired',
                        'severity' => 'high',
                        'expires_at' => $doc->expires_at?->format('Y-m-d'),
                        'days_overdue' => abs($doc->days_until_expiration),
                    ];
                }
            }

            // Documents expirant bientôt
            if ($filter === 'all' || $filter === 'expiring_soon') {
                $expiringSoonDocs = $client->complianceDocuments()
                    ->expiringSoon(90)
                    ->where('status', 'validated')
                    ->get();

                foreach ($expiringSoonDocs as $doc) {
                    $alerts[] = [
                        'client_id' => $client->id,
                        'client_name' => trim("{$client->prenom} {$client->nom}"),
                        'document_type' => $doc->document_type,
                        'document_label' => $doc->document_label,
                        'issue' => 'expiring_soon',
                        'severity' => $doc->days_until_expiration <= 30 ? 'medium' : 'low',
                        'expires_at' => $doc->expires_at?->format('Y-m-d'),
                        'days_until_expiration' => $doc->days_until_expiration,
                    ];
                }
            }
        }

        // Trier les alertes
        usort($alerts, function ($a, $b) {
            $severityOrder = ['high' => 0, 'medium' => 1, 'low' => 2];
            $severityDiff = $severityOrder[$a['severity']] - $severityOrder[$b['severity']];

            if ($severityDiff !== 0) {
                return $severityDiff;
            }

            return strcmp($a['expires_at'] ?? '', $b['expires_at'] ?? '');
        });

        // Pagination manuelle
        $total = count($alerts);
        $page = $request->input('page', 1);
        $offset = ($page - 1) * $perPage;
        $paginatedAlerts = array_slice($alerts, $offset, $perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'alerts' => $paginatedAlerts,
                'pagination' => [
                    'total' => $total,
                    'per_page' => $perPage,
                    'current_page' => (int) $page,
                    'last_page' => ceil($total / $perPage),
                ],
            ],
        ]);
    }
}
