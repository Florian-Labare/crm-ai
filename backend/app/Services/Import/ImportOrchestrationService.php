<?php

namespace App\Services\Import;

use App\Models\Client;
use App\Models\ImportRow;
use App\Models\ImportSession;
use App\Services\EnfantSyncService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportOrchestrationService
{
    public function __construct(
        private ImportFileParserService $parser,
        private ImportMappingService $mappingService,
        private ImportValidationService $validator,
        private ImportDuplicateDetectionService $duplicateDetector,
        private EnfantSyncService $enfantSyncService
    ) {
    }

    public function analyzeFile(ImportSession $session): void
    {
        $session->update(['status' => ImportSession::STATUS_ANALYZING]);

        try {
            $filePath = storage_path('app/' . $session->file_path);

            $columns = $this->parser->detectColumns($filePath);
            $rowCount = $this->parser->getRowCount($filePath);
            $suggestedMappings = $this->mappingService->suggestMappings($columns);

            $session->update([
                'detected_columns' => $columns,
                'ai_suggested_mappings' => $suggestedMappings,
                'total_rows' => $rowCount,
                'status' => ImportSession::STATUS_MAPPING,
            ]);

            Log::info('Import file analyzed', [
                'session_id' => $session->id,
                'columns' => count($columns),
                'rows' => $rowCount,
            ]);
        } catch (\Exception $e) {
            $session->update([
                'status' => ImportSession::STATUS_FAILED,
                'errors_summary' => ['analysis_error' => $e->getMessage()],
            ]);

            Log::error('Import analysis failed', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function processSession(ImportSession $session): void
    {
        $session->update([
            'status' => ImportSession::STATUS_PROCESSING,
            'started_at' => now(),
        ]);

        try {
            $filePath = storage_path('app/' . $session->file_path);
            $mapping = $session->mapping;

            if (!$mapping) {
                throw new \Exception('Aucun mapping configuré pour cette session');
            }

            $columnMappings = $mapping->column_mappings;
            $parsed = $this->parser->parseFile($filePath);

            $rowNumber = 0;
            foreach ($parsed['rows'] as $rawRow) {
                $rowNumber++;

                $mappedData = $this->mappingService->applyMapping($rawRow, $columnMappings);

                ImportRow::create([
                    'import_session_id' => $session->id,
                    'row_number' => $rowNumber,
                    'raw_data' => $rawRow,
                    'normalized_data' => null,
                    'status' => ImportRow::STATUS_PENDING,
                ]);
            }

            $session->update(['total_rows' => $rowNumber]);

            Log::info('Import rows created', [
                'session_id' => $session->id,
                'total_rows' => $rowNumber,
            ]);
        } catch (\Exception $e) {
            $session->update([
                'status' => ImportSession::STATUS_FAILED,
                'errors_summary' => ['processing_error' => $e->getMessage()],
            ]);

            throw $e;
        }
    }

    public function processBatch(ImportSession $session, int $offset, int $limit = 50): array
    {
        $mapping = $session->mapping;
        if (!$mapping) {
            throw new \Exception('Aucun mapping configuré');
        }

        $columnMappings = $mapping->column_mappings;

        $rows = ImportRow::where('import_session_id', $session->id)
            ->where('status', ImportRow::STATUS_PENDING)
            ->orderBy('row_number')
            ->skip($offset)
            ->take($limit)
            ->get();

        $results = [
            'processed' => 0,
            'valid' => 0,
            'invalid' => 0,
            'duplicates' => 0,
        ];

        foreach ($rows as $row) {
            $mappedData = $this->mappingService->applyMapping($row->raw_data, $columnMappings);

            $validationResult = $this->validator->validateAndNormalize($mappedData);

            $row->normalized_data = $validationResult['data'];

            if (!$validationResult['is_valid']) {
                $row->status = ImportRow::STATUS_INVALID;
                $row->validation_errors = $validationResult['errors'];
                $row->save();
                $results['invalid']++;
                $results['processed']++;
                continue;
            }

            $duplicateResult = $this->duplicateDetector->findDuplicates(
                $validationResult['data'],
                $session->team_id
            );

            if ($duplicateResult['has_duplicates']) {
                $row->status = ImportRow::STATUS_DUPLICATE;
                $row->duplicate_matches = $duplicateResult['matches'];
                $row->duplicate_confidence = $duplicateResult['confidence'];
                if ($duplicateResult['best_match']) {
                    $row->matched_client_id = $duplicateResult['best_match']['client_id'];
                }
                $row->save();
                $results['duplicates']++;
            } else {
                $row->status = ImportRow::STATUS_VALID;
                $row->save();
                $results['valid']++;
            }

            $results['processed']++;
        }

        $session->increment('processed_rows', $results['processed']);
        $session->increment('error_count', $results['invalid']);
        $session->increment('duplicate_count', $results['duplicates']);

        if ($session->processed_rows >= $session->total_rows) {
            $session->update([
                'status' => ImportSession::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);
        }

        return $results;
    }

    public function importRow(ImportRow $row, string $action = 'create'): ?Client
    {
        if (!in_array($row->status, [ImportRow::STATUS_VALID, ImportRow::STATUS_DUPLICATE])) {
            throw new \Exception('La ligne doit être valide ou doublon pour être importée');
        }

        $data = $row->normalized_data;
        $session = $row->session;

        return DB::transaction(function () use ($row, $data, $session, $action) {
            $client = null;

            if ($action === 'create' || ($action === 'auto' && !$row->matched_client_id)) {
                $client = $this->createClient($data, $session->team_id, $session->user_id);
            } elseif ($action === 'merge' && $row->matched_client_id) {
                $client = $this->mergeWithExisting($row->matched_client_id, $data);
            } elseif ($action === 'skip') {
                $row->update(['status' => ImportRow::STATUS_IMPORTED]);

                return null;
            }

            if ($client) {
                if (isset($data['conjoint']) && !empty($data['conjoint'])) {
                    $this->createOrUpdateConjoint($client, $data['conjoint']);
                }

                if (isset($data['enfants']) && !empty($data['enfants'])) {
                    $this->enfantSyncService->sync($client, $data['enfants']);
                }

                $row->update([
                    'status' => ImportRow::STATUS_IMPORTED,
                    'matched_client_id' => $client->id,
                ]);

                $session->increment('success_count');
            }

            return $client;
        });
    }

    public function importValidRows(ImportSession $session): int
    {
        $validRows = ImportRow::where('import_session_id', $session->id)
            ->where('status', ImportRow::STATUS_VALID)
            ->get();

        $imported = 0;

        foreach ($validRows as $row) {
            try {
                $this->importRow($row, 'create');
                $imported++;
            } catch (\Exception $e) {
                Log::error('Failed to import row', [
                    'row_id' => $row->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $imported;
    }

    public function getSessionStats(ImportSession $session): array
    {
        $statusCounts = ImportRow::where('import_session_id', $session->id)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'total_rows' => $session->total_rows,
            'processed_rows' => $session->processed_rows,
            'progress_percentage' => $session->progress_percentage,
            'pending' => $statusCounts[ImportRow::STATUS_PENDING] ?? 0,
            'valid' => $statusCounts[ImportRow::STATUS_VALID] ?? 0,
            'invalid' => $statusCounts[ImportRow::STATUS_INVALID] ?? 0,
            'duplicate' => $statusCounts[ImportRow::STATUS_DUPLICATE] ?? 0,
            'imported' => $statusCounts[ImportRow::STATUS_IMPORTED] ?? 0,
            'success_count' => $session->success_count,
            'error_count' => $session->error_count,
            'duplicate_count' => $session->duplicate_count,
        ];
    }

    private function createClient(array $data, int $teamId, int $userId): Client
    {
        $clientData = array_filter([
            'team_id' => $teamId,
            'user_id' => $userId,
            'nom' => $data['nom'] ?? null,
            'prenom' => $data['prenom'] ?? null,
            'email' => $data['email'] ?? null,
            'telephone' => $data['telephone'] ?? null,
            'date_naissance' => $data['date_naissance'] ?? null,
            'lieu_naissance' => $data['lieu_naissance'] ?? null,
            'nationalite' => $data['nationalite'] ?? null,
            'adresse' => $data['adresse'] ?? null,
            'code_postal' => $data['code_postal'] ?? null,
            'ville' => $data['ville'] ?? null,
            'civilite' => $data['civilite'] ?? null,
            'situation_matrimoniale' => $data['situation_matrimoniale'] ?? null,
            'profession' => $data['profession'] ?? null,
            'situation_actuelle' => $data['situation_actuelle'] ?? null,
            'revenus_annuels' => $data['revenus_annuels'] ?? null,
            'fumeur' => $data['fumeur'] ?? null,
            'chef_entreprise' => $data['chef_entreprise'] ?? null,
        ], fn($v) => $v !== null);

        return Client::create($clientData);
    }

    private function mergeWithExisting(int $clientId, array $data): Client
    {
        $client = Client::findOrFail($clientId);

        $updateData = [];
        $fieldsToMerge = [
            'email', 'telephone', 'adresse', 'code_postal', 'ville',
            'profession', 'situation_actuelle', 'revenus_annuels',
        ];

        foreach ($fieldsToMerge as $field) {
            if (!empty($data[$field]) && empty($client->$field)) {
                $updateData[$field] = $data[$field];
            }
        }

        if (!empty($updateData)) {
            $client->update($updateData);
        }

        return $client->fresh();
    }

    private function createOrUpdateConjoint(Client $client, array $conjointData): void
    {
        $conjoint = $client->conjoint;

        $data = array_filter([
            'client_id' => $client->id,
            'nom' => $conjointData['nom'] ?? null,
            'prenom' => $conjointData['prenom'] ?? null,
            'date_naissance' => $conjointData['date_naissance'] ?? null,
            'profession' => $conjointData['profession'] ?? null,
            'email' => $conjointData['email'] ?? null,
            'telephone' => $conjointData['telephone'] ?? null,
        ], fn($v) => $v !== null);

        if ($conjoint) {
            $conjoint->update($data);
        } else {
            $client->conjoint()->create($data);
        }
    }
}
