<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ComplianceRequirement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComplianceDashboardController extends Controller {
    /**
     * Retourne les statistiques globales de conformité — 2 requêtes SQL au lieu de 2N+1.
     */
    public function index(Request $request): JsonResponse {
        $user = auth()->user();

        // 1 requête : clients avec documents pré-chargés
        $clients = Client::with(['complianceDocuments' => function ($q) {
            $q->where('status', 'validated')
                ->select('id', 'client_id', 'document_type', 'status', 'expires_at', 'created_at');
        }])->get();

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

        // 1 requête : documents globaux obligatoires
        $globalRequirements = ComplianceRequirement::where('besoin', 'global')
            ->where('is_mandatory', true)
            ->pluck('document_type')
            ->toArray();

        $totalGlobalRequired = count($globalRequirements);
        $now = now();
        $soonCutoff = $now->copy()->addDays(90);

        $fullyCompliant = 0;
        $partiallyCompliant = 0;
        $nonCompliant = 0;
        $withExpiredDocs = 0;
        $withExpiringSoon = 0;
        $alerts = [];

        foreach ($clients as $client) {
            $docs = $client->complianceDocuments;

            // Conformité globale : docs validés non expirés pour les exigences globales
            $validTypes = $docs
                ->filter(fn ($d) => in_array($d->document_type, $globalRequirements, true)
                    && ($d->expires_at === null || $d->expires_at->gt($now))
                )
                ->pluck('document_type')
                ->unique()
                ->count();

            if ($validTypes === $totalGlobalRequired) {
                $fullyCompliant++;
            } elseif ($validTypes > 0) {
                $partiallyCompliant++;
            } else {
                $nonCompliant++;
            }

            // Documents expirés (calcul en mémoire)
            $expired = $docs->filter(fn ($d) => $d->expires_at && $d->expires_at->lt($now));
            if ($expired->isNotEmpty()) {
                $withExpiredDocs++;
                foreach ($expired as $doc) {
                    $alerts[] = [
                        'client_id' => $client->id,
                        'client_name' => trim("{$client->prenom} {$client->nom}"),
                        'document_type' => $doc->document_type,
                        'document_label' => $doc->document_label,
                        'issue' => 'expired',
                        'severity' => 'high',
                        'expires_at' => $doc->expires_at->format('Y-m-d'),
                        'days_overdue' => (int) $now->diffInDays($doc->expires_at),
                    ];
                }
            }

            // Documents expirant dans 90 jours (calcul en mémoire)
            $expiringSoon = $docs->filter(fn ($d) => $d->expires_at
                && $d->expires_at->gt($now)
                && $d->expires_at->lte($soonCutoff)
            );
            if ($expiringSoon->isNotEmpty()) {
                $withExpiringSoon++;
                foreach ($expiringSoon as $doc) {
                    $daysLeft = (int) $now->diffInDays($doc->expires_at);
                    $alerts[] = [
                        'client_id' => $client->id,
                        'client_name' => trim("{$client->prenom} {$client->nom}"),
                        'document_type' => $doc->document_type,
                        'document_label' => $doc->document_label,
                        'issue' => 'expiring_soon',
                        'severity' => $daysLeft <= 30 ? 'medium' : 'low',
                        'expires_at' => $doc->expires_at->format('Y-m-d'),
                        'days_until_expiration' => $daysLeft,
                    ];
                }
            }
        }

        usort($alerts, function ($a, $b) {
            $order = ['high' => 0, 'medium' => 1, 'low' => 2];
            $diff = $order[$a['severity']] - $order[$b['severity']];

            return $diff !== 0 ? $diff : strcmp($a['expires_at'] ?? '', $b['expires_at'] ?? '');
        });

        $alerts = array_slice($alerts, 0, (int) $request->input('limit', 50));

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
     * Retourne uniquement les alertes (paginées) — 1 requête SQL.
     */
    public function alerts(Request $request): JsonResponse {
        $perPage = (int) $request->input('per_page', 20);
        $filter = $request->input('filter', 'all');

        $clients = Client::with(['complianceDocuments' => function ($q) {
            $q->where('status', 'validated')
                ->whereNotNull('expires_at')
                ->select('id', 'client_id', 'document_type', 'status', 'expires_at', 'created_at');
        }])->get();

        $now = now();
        $soonCutoff = $now->copy()->addDays(90);
        $alerts = [];

        foreach ($clients as $client) {
            $docs = $client->complianceDocuments;

            if ($filter === 'all' || $filter === 'expired') {
                foreach ($docs->filter(fn ($d) => $d->expires_at->lt($now)) as $doc) {
                    $alerts[] = [
                        'client_id' => $client->id,
                        'client_name' => trim("{$client->prenom} {$client->nom}"),
                        'document_type' => $doc->document_type,
                        'document_label' => $doc->document_label,
                        'issue' => 'expired',
                        'severity' => 'high',
                        'expires_at' => $doc->expires_at->format('Y-m-d'),
                        'days_overdue' => (int) $now->diffInDays($doc->expires_at),
                    ];
                }
            }

            if ($filter === 'all' || $filter === 'expiring_soon') {
                foreach ($docs->filter(fn ($d) => $d->expires_at->gt($now) && $d->expires_at->lte($soonCutoff)) as $doc) {
                    $daysLeft = (int) $now->diffInDays($doc->expires_at);
                    $alerts[] = [
                        'client_id' => $client->id,
                        'client_name' => trim("{$client->prenom} {$client->nom}"),
                        'document_type' => $doc->document_type,
                        'document_label' => $doc->document_label,
                        'issue' => 'expiring_soon',
                        'severity' => $daysLeft <= 30 ? 'medium' : 'low',
                        'expires_at' => $doc->expires_at->format('Y-m-d'),
                        'days_until_expiration' => $daysLeft,
                    ];
                }
            }
        }

        usort($alerts, function ($a, $b) {
            $order = ['high' => 0, 'medium' => 1, 'low' => 2];
            $diff = $order[$a['severity']] - $order[$b['severity']];

            return $diff !== 0 ? $diff : strcmp($a['expires_at'] ?? '', $b['expires_at'] ?? '');
        });

        $total = count($alerts);
        $page = (int) $request->input('page', 1);
        $offset = ($page - 1) * $perPage;

        return response()->json([
            'success' => true,
            'data' => [
                'alerts' => array_slice($alerts, $offset, $perPage),
                'pagination' => [
                    'total' => $total,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'last_page' => (int) ceil($total / $perPage),
                ],
            ],
        ]);
    }
}
