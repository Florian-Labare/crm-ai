<?php

namespace App\Http\Controllers;

use App\Jobs\AnalyzeImportFileJob;
use App\Jobs\ProcessImportSessionJob;
use App\Models\ImportMapping;
use App\Models\ImportRow;
use App\Models\ImportSession;
use App\Services\Import\ImportMappingService;
use App\Services\Import\ImportOrchestrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ImportSessionController extends Controller
{
    public function __construct(
        private ImportOrchestrationService $orchestrator,
        private ImportMappingService $mappingService
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
            'file' => 'required|file|mimes:xlsx,xls,csv|max:51200',
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
        ]);

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

    public function start(ImportSession $session): JsonResponse
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

    public function destroy(ImportSession $session): JsonResponse
    {
        if (Storage::exists($session->file_path)) {
            Storage::delete($session->file_path);
        }

        $session->rows()->delete();
        $session->delete();

        return response()->json([
            'success' => true,
            'message' => 'Session supprimée',
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
            default => 'excel',
        };
    }
}
