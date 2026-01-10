<?php

namespace App\Http\Controllers;

use App\Jobs\AnalyzeImportFileJob;
use App\Jobs\ProcessImportSessionJob;
use App\Models\ImportAuditLog;
use App\Models\ImportMapping;
use App\Models\ImportRow;
use App\Models\ImportSession;
use App\Services\Import\ImportMappingService;
use App\Services\Import\ImportOrchestrationService;
use App\Services\Import\RgpdComplianceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ImportSessionController extends Controller
{
    public function __construct(
        private ImportOrchestrationService $orchestrator,
        private ImportMappingService $mappingService,
        private RgpdComplianceService $rgpdService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $teamId = $request->user()->current_team_id;

        $sessions = ImportSession::where('team_id', $teamId)
            ->with('user:id,name', 'mapping:id,name')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $sessions,
        ]);
    }

    public function upload(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,json,xml,sql,txt|max:51200',
            'import_mapping_id' => 'nullable|exists:import_mappings,id',
        ]);

        $file = $request->file('file');
        $filename = time() . '_' . $file->getClientOriginalName();
        $path = $file->storeAs('imports', $filename);

        $session = ImportSession::create([
            'team_id' => $request->user()->current_team_id,
            'user_id' => $request->user()->id,
            'import_mapping_id' => $validated['import_mapping_id'] ?? null,
            'original_filename' => $file->getClientOriginalName(),
            'file_path' => $path,
            'status' => ImportSession::STATUS_PENDING,
            'retention_until' => now()->addDays(90), // RGPD: 90 days retention
        ]);

        // RGPD: Log the upload action
        $this->rgpdService->logAction(
            ImportAuditLog::ACTION_UPLOAD,
            ImportAuditLog::RESOURCE_SESSION,
            $session->id,
            [
                'import_session_id' => $session->id,
                'metadata' => [
                    'filename' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                ],
            ],
            $request
        );

        AnalyzeImportFileJob::dispatch($session);

        return response()->json([
            'success' => true,
            'message' => 'Fichier uploadé, analyse en cours',
            'data' => $session,
        ], 201);
    }

    public function show(ImportSession $session): JsonResponse
    {
        $session->load('user:id,name', 'mapping');

        $stats = $this->orchestrator->getSessionStats($session);

        return response()->json([
            'success' => true,
            'data' => [
                'session' => $session,
                'stats' => $stats,
            ],
        ]);
    }

    public function setMapping(Request $request, ImportSession $session): JsonResponse
    {
        if ($session->status !== ImportSession::STATUS_MAPPING) {
            return response()->json([
                'success' => false,
                'message' => 'La session n\'est pas en attente de mapping',
            ], 422);
        }

        $validated = $request->validate([
            'import_mapping_id' => 'nullable|exists:import_mappings,id',
            'column_mappings' => 'required_without:import_mapping_id|array',
            'save_as_template' => 'boolean',
            'template_name' => 'required_if:save_as_template,true|string|max:255',
        ]);

        if (isset($validated['import_mapping_id'])) {
            $session->update(['import_mapping_id' => $validated['import_mapping_id']]);
        } else {
            $errors = $this->mappingService->validateMapping($validated['column_mappings']);
            if (!empty($errors)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mapping invalide',
                    'errors' => $errors,
                ], 422);
            }

            if (!empty($validated['save_as_template']) && !empty($validated['template_name'])) {
                $mapping = $this->mappingService->createMapping(
                    $request->user()->current_team_id,
                    $validated['template_name'],
                    $this->detectSourceType($session->original_filename),
                    $validated['column_mappings']
                );
                $session->update(['import_mapping_id' => $mapping->id]);
            } else {
                $mapping = $this->mappingService->createMapping(
                    $request->user()->current_team_id,
                    'Import ' . now()->format('Y-m-d H:i'),
                    $this->detectSourceType($session->original_filename),
                    $validated['column_mappings']
                );
                $session->update(['import_mapping_id' => $mapping->id]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Mapping configuré',
            'data' => $session->fresh('mapping'),
        ]);
    }

    /**
     * Record RGPD consent for import session
     */
    public function recordConsent(Request $request, ImportSession $session): JsonResponse
    {
        $validated = $request->validate([
            'legal_basis' => 'required|in:consent,contract,legal_obligation,vital_interests,public_task,legitimate_interest',
            'legal_basis_details' => 'required|string|max:1000',
            'confirm_authorization' => 'required|accepted',
        ]);

        $this->rgpdService->recordConsent(
            $session,
            $validated['legal_basis'],
            $validated['legal_basis_details'],
            $request
        );

        return response()->json([
            'success' => true,
            'message' => 'Consentement RGPD enregistré',
            'data' => $session->fresh(),
        ]);
    }

    /**
     * Get legal basis options for RGPD consent
     */
    public function legalBases(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ImportAuditLog::getLegalBasesLabels(),
        ]);
    }

    public function start(Request $request, ImportSession $session): JsonResponse
    {
        if (!$session->import_mapping_id) {
            return response()->json([
                'success' => false,
                'message' => 'Veuillez configurer le mapping avant de lancer l\'import',
            ], 422);
        }

        if (!in_array($session->status, [ImportSession::STATUS_MAPPING, ImportSession::STATUS_PENDING])) {
            return response()->json([
                'success' => false,
                'message' => 'L\'import ne peut pas être lancé dans cet état',
            ], 422);
        }

        // RGPD: Require consent before starting import
        if (!$session->hasRgpdConsent()) {
            return response()->json([
                'success' => false,
                'message' => 'Consentement RGPD requis avant de lancer l\'import',
                'requires_consent' => true,
            ], 422);
        }

        // RGPD: Log the start action
        $this->rgpdService->logAction(
            ImportAuditLog::ACTION_IMPORT,
            ImportAuditLog::RESOURCE_SESSION,
            $session->id,
            [
                'import_session_id' => $session->id,
                'legal_basis' => $session->legal_basis,
                'consent_confirmed' => true,
                'metadata' => [
                    'total_rows' => $session->total_rows,
                ],
            ],
            $request
        );

        ProcessImportSessionJob::dispatch($session);

        return response()->json([
            'success' => true,
            'message' => 'Import lancé',
            'data' => $session,
        ]);
    }

    public function rows(Request $request, ImportSession $session): JsonResponse
    {
        $query = ImportRow::where('import_session_id', $session->id);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $rows = $query->orderBy('row_number')
            ->paginate($request->get('per_page', 50));

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function resolveRow(Request $request, ImportSession $session, ImportRow $row): JsonResponse
    {
        if ($row->import_session_id !== $session->id) {
            return response()->json([
                'success' => false,
                'message' => 'Ligne non trouvée dans cette session',
            ], 404);
        }

        $validated = $request->validate([
            'action' => 'required|in:create,merge,skip',
        ]);

        try {
            $client = $this->orchestrator->importRow($row, $validated['action']);

            return response()->json([
                'success' => true,
                'message' => 'Ligne traitée',
                'data' => [
                    'row' => $row->fresh(),
                    'client_id' => $client?->id,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function importValid(ImportSession $session): JsonResponse
    {
        $imported = $this->orchestrator->importValidRows($session);

        return response()->json([
            'success' => true,
            'message' => "{$imported} clients importés",
            'data' => [
                'imported_count' => $imported,
                'stats' => $this->orchestrator->getSessionStats($session),
            ],
        ]);
    }

    public function destroy(Request $request, ImportSession $session): JsonResponse
    {
        // RGPD: Use the compliance service to properly delete and log
        $result = $this->rgpdService->deleteSessionData($session, $request);

        return response()->json([
            'success' => true,
            'message' => 'Session supprimée',
            'data' => $result,
        ]);
    }

    /**
     * Get audit trail for a session
     */
    public function auditTrail(ImportSession $session): JsonResponse
    {
        $trail = $this->rgpdService->getSessionAuditTrail($session);

        return response()->json([
            'success' => true,
            'data' => $trail,
        ]);
    }

    /**
     * Get clients imported from this session
     */
    public function importedClients(ImportSession $session): JsonResponse
    {
        $clients = $this->rgpdService->getClientsFromSession($session);

        return response()->json([
            'success' => true,
            'data' => $clients,
        ]);
    }

    public function suggestMappings(ImportSession $session): JsonResponse
    {
        if (empty($session->detected_columns)) {
            return response()->json([
                'success' => false,
                'message' => 'Les colonnes n\'ont pas encore été détectées',
            ], 422);
        }

        $suggestions = $this->mappingService->suggestMappings($session->detected_columns);

        return response()->json([
            'success' => true,
            'data' => [
                'detected_columns' => $session->detected_columns,
                'suggestions' => $suggestions,
                'ai_suggestions' => $session->ai_suggested_mappings,
            ],
        ]);
    }

    private function detectSourceType(string $filename): string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return match ($extension) {
            'csv' => 'csv',
            'xlsx', 'xls' => 'excel',
            'json' => 'json',
            'xml' => 'xml',
            'sql', 'txt' => 'sql',
            default => 'excel',
        };
    }
}
