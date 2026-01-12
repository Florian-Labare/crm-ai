<?php

namespace App\Http\Controllers;

use App\Models\ImportMapping;
use App\Services\Import\ImportFieldsService;
use App\Services\Import\ImportMappingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportMappingController extends Controller
{
    public function __construct(
        private ImportMappingService $mappingService,
        private ImportFieldsService $fieldsService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $teamId = $request->user()->currentTeam()?->id;

        $mappings = $this->mappingService->getTeamMappings($teamId);

        return response()->json([
            'success' => true,
            'data' => $mappings,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'source_type' => 'required|string|in:excel,csv,sql',
            'column_mappings' => 'required|array',
            'default_values' => 'nullable|array',
        ]);

        $errors = $this->mappingService->validateMapping($validated['column_mappings']);
        if (!empty($errors)) {
            return response()->json([
                'success' => false,
                'message' => 'Mapping invalide',
                'errors' => $errors,
            ], 422);
        }

        $mapping = $this->mappingService->createMapping(
            $request->user()->currentTeam()?->id,
            $validated['name'],
            $validated['source_type'],
            $validated['column_mappings'],
            $validated['default_values'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Mapping créé avec succès',
            'data' => $mapping,
        ], 201);
    }

    public function show(ImportMapping $mapping): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $mapping,
        ]);
    }

    public function update(Request $request, ImportMapping $mapping): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'source_type' => 'sometimes|string|in:excel,csv,sql',
            'column_mappings' => 'sometimes|array',
            'default_values' => 'nullable|array',
        ]);

        if (isset($validated['column_mappings'])) {
            $errors = $this->mappingService->validateMapping($validated['column_mappings']);
            if (!empty($errors)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mapping invalide',
                    'errors' => $errors,
                ], 422);
            }
        }

        $mapping = $this->mappingService->updateMapping($mapping, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Mapping mis à jour',
            'data' => $mapping,
        ]);
    }

    public function destroy(ImportMapping $mapping): JsonResponse
    {
        $mapping->delete();

        return response()->json([
            'success' => true,
            'message' => 'Mapping supprimé',
        ]);
    }

    public function availableFields(): JsonResponse
    {
        // Format legacy pour compatibilité frontend actuel
        $legacyFields = $this->mappingService->getAvailableTargetFields();

        // Format enrichi avec labels français et groupes
        $grouped = $this->fieldsService->getGroupedFieldsForSelect();
        $flat = $this->fieldsService->getFlatFieldsList();

        // Fusion: format legacy + données enrichies
        $data = $legacyFields;
        $data['_enhanced'] = [
            'grouped' => $grouped,
            'flat' => $flat,
            'total_fields' => count($flat),
        ];

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
