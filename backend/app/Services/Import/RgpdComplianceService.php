<?php

namespace App\Services\Import;

use App\Models\ImportAuditLog;
use App\Models\ImportSession;
use App\Models\DatabaseConnection;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class RgpdComplianceService
{
    /**
     * Default retention period for import sessions (in days)
     */
    private const DEFAULT_RETENTION_DAYS = 90;

    /**
     * Log an import action
     */
    public function logAction(
        string $action,
        string $resourceType,
        ?int $resourceId = null,
        array $options = [],
        ?Request $request = null
    ): ImportAuditLog {
        return ImportAuditLog::logAction($action, $resourceType, $resourceId, $options, $request);
    }

    /**
     * Validate and record RGPD consent for an import session
     */
    public function recordConsent(
        ImportSession $session,
        string $legalBasis,
        string $legalBasisDetails,
        Request $request
    ): void {
        $session->update([
            'rgpd_consent_given' => true,
            'legal_basis' => $legalBasis,
            'legal_basis_details' => $legalBasisDetails,
            'consent_timestamp' => now(),
            'retention_until' => now()->addDays(self::DEFAULT_RETENTION_DAYS),
        ]);

        $this->logAction(
            ImportAuditLog::ACTION_CONSENT,
            ImportAuditLog::RESOURCE_SESSION,
            $session->id,
            [
                'import_session_id' => $session->id,
                'legal_basis' => $legalBasis,
                'legal_basis_details' => $legalBasisDetails,
                'consent_confirmed' => true,
            ],
            $request
        );

        Log::info('RGPD consent recorded', [
            'session_id' => $session->id,
            'legal_basis' => $legalBasis,
            'user_id' => $request->user()->id,
        ]);
    }

    /**
     * Check if session has valid RGPD consent
     */
    public function hasValidConsent(ImportSession $session): bool
    {
        return $session->rgpd_consent_given
            && $session->legal_basis !== null
            && $session->consent_timestamp !== null;
    }

    /**
     * Mark client as imported with source tracking
     */
    public function markClientAsImported(
        Client $client,
        ImportSession $session,
        string $source = 'file_import'
    ): void {
        $client->update([
            'import_source' => $source,
            'import_session_id' => $session->id,
            'imported_at' => now(),
        ]);
    }

    /**
     * Create ephemeral database connection (not stored)
     */
    public function createEphemeralConnection(array $config, int $teamId, int $userId): array
    {
        // Don't store in database, just return config for immediate use
        $this->logAction(
            ImportAuditLog::ACTION_CONNECT,
            ImportAuditLog::RESOURCE_CONNECTION,
            null,
            [
                'team_id' => $teamId,
                'user_id' => $userId,
                'metadata' => [
                    'driver' => $config['driver'],
                    'host' => $config['host'] ?? 'N/A',
                    'database' => $config['database'],
                    'ephemeral' => true,
                ],
            ]
        );

        return $config;
    }

    /**
     * Purge expired import sessions and their data
     */
    public function purgeExpiredSessions(): int
    {
        $expiredSessions = ImportSession::where('retention_until', '<', now())
            ->whereNotNull('retention_until')
            ->get();

        $count = 0;

        foreach ($expiredSessions as $session) {
            try {
                // Log before deletion
                $this->logAction(
                    ImportAuditLog::ACTION_DELETE,
                    ImportAuditLog::RESOURCE_SESSION,
                    $session->id,
                    [
                        'team_id' => $session->team_id,
                        'metadata' => [
                            'reason' => 'retention_expired',
                            'retention_until' => $session->retention_until,
                            'rows_deleted' => $session->rows()->count(),
                        ],
                    ]
                );

                // Delete rows first
                $session->rows()->delete();

                // Delete file if exists
                if ($session->file_path && \Storage::exists($session->file_path)) {
                    \Storage::delete($session->file_path);
                }

                // Delete session
                $session->delete();

                $count++;
            } catch (\Exception $e) {
                Log::error('Failed to purge expired session', [
                    'session_id' => $session->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Purged expired import sessions', ['count' => $count]);

        return $count;
    }

    /**
     * Get audit trail for a session
     */
    public function getSessionAuditTrail(ImportSession $session): array
    {
        return ImportAuditLog::where('import_session_id', $session->id)
            ->with('user:id,name')
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Get audit trail for a team
     */
    public function getTeamAuditTrail(int $teamId, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $query = ImportAuditLog::forTeam($teamId)
            ->with('user:id,name');

        if ($from && $to) {
            $query->dateRange($from, $to);
        }

        return $query->orderBy('created_at', 'desc')
            ->limit(500)
            ->get()
            ->toArray();
    }

    /**
     * Export audit logs for RGPD compliance (data portability)
     */
    public function exportAuditLogs(int $teamId): array
    {
        $logs = ImportAuditLog::forTeam($teamId)
            ->with(['user:id,name,email', 'importSession:id,original_filename'])
            ->orderBy('created_at', 'asc')
            ->get();

        return [
            'exported_at' => now()->toIso8601String(),
            'team_id' => $teamId,
            'total_records' => $logs->count(),
            'logs' => $logs->map(function ($log) {
                return [
                    'timestamp' => $log->created_at->toIso8601String(),
                    'action' => $log->action,
                    'resource_type' => $log->resource_type,
                    'resource_id' => $log->resource_id,
                    'user' => $log->user?->name ?? 'Unknown',
                    'legal_basis' => $log->legal_basis,
                    'consent_confirmed' => $log->consent_confirmed,
                    'ip_address' => $log->ip_address,
                    'success' => $log->success,
                    'records_affected' => $log->records_affected,
                ];
            })->toArray(),
        ];
    }

    /**
     * Get clients imported from a specific session (for right to erasure)
     */
    public function getClientsFromSession(ImportSession $session): array
    {
        return Client::where('import_session_id', $session->id)
            ->select('id', 'nom', 'prenom', 'email', 'imported_at')
            ->get()
            ->toArray();
    }

    /**
     * Delete all data related to an import session (right to erasure)
     */
    public function deleteSessionData(ImportSession $session, Request $request): array
    {
        $clientsDeleted = 0;
        $rowsDeleted = 0;

        // Count affected clients
        $clients = Client::where('import_session_id', $session->id)->get();
        $clientsDeleted = $clients->count();

        // Clear import reference from clients (don't delete clients, just unlink)
        Client::where('import_session_id', $session->id)->update([
            'import_session_id' => null,
            'import_source' => 'unlinked_' . $session->id,
        ]);

        // Delete rows
        $rowsDeleted = $session->rows()->count();
        $session->rows()->delete();

        // Delete file
        if ($session->file_path && \Storage::exists($session->file_path)) {
            \Storage::delete($session->file_path);
        }

        // Log deletion
        $this->logAction(
            ImportAuditLog::ACTION_DELETE,
            ImportAuditLog::RESOURCE_SESSION,
            $session->id,
            [
                'import_session_id' => $session->id,
                'metadata' => [
                    'reason' => 'user_request',
                    'clients_unlinked' => $clientsDeleted,
                    'rows_deleted' => $rowsDeleted,
                ],
                'records_affected' => $clientsDeleted + $rowsDeleted,
            ],
            $request
        );

        // Delete session
        $session->delete();

        return [
            'clients_unlinked' => $clientsDeleted,
            'rows_deleted' => $rowsDeleted,
        ];
    }
}
