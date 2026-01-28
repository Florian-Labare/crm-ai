<?php

namespace App\Services\Import;

use App\Models\BaeEpargne;
use App\Models\BaePrevoyance;
use App\Models\BaeRetraite;
use App\Models\Client;
use App\Models\ClientActifFinancier;
use App\Models\ClientAutreEpargne;
use App\Models\ClientBienImmobilier;
use App\Models\ClientPassif;
use App\Models\ClientRevenu;
use App\Models\ImportRow;
use App\Models\ImportSession;
use App\Models\SanteSouhait;
use App\Services\EnfantSyncService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImportOrchestrationService
{
    public function __construct(
        private ImportFileParserService $parser,
        private ImportMappingService $mappingService,
        private ImportValidationService $validator,
        private ImportDuplicateDetectionService $duplicateDetector,
        private DatabaseConnectorService $databaseConnector,
        private EnfantSyncService $enfantSyncService
    ) {
    }

    public function analyzeFile(ImportSession $session): void
    {
        $session->update(['status' => ImportSession::STATUS_ANALYZING]);

        try {
            // Check if this is a database import or file import
            if ($session->isDatabaseImport()) {
                $this->analyzeDatabaseSource($session);
            } else {
                $this->analyzeFileSource($session);
            }
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

    private function analyzeFileSource(ImportSession $session): void
    {
        $filePath = Storage::path($session->file_path);

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
    }

    private function analyzeDatabaseSource(ImportSession $session): void
    {
        $connection = $session->databaseConnection;
        if (!$connection) {
            throw new \Exception('Connexion base de données non trouvée');
        }

        $config = $connection->getConnectionConfig();

        // Get columns from table or query
        if ($session->source_table) {
            $columns = $this->databaseConnector->getTableColumns($config, $session->source_table);
            $columnNames = array_map(fn($col) => $col['name'], $columns);
            $rowCount = $this->databaseConnector->getTableRowCount($config, $session->source_table);
        } else {
            // For custom query, execute with limit to get columns
            $sampleData = $this->databaseConnector->executeQuery($config, $session->source_query, 1);
            $columnNames = !empty($sampleData) ? array_keys($sampleData[0]) : [];
            $rowCount = 0; // Will be determined during processing
        }

        $suggestedMappings = $this->mappingService->suggestMappings($columnNames);

        $session->update([
            'detected_columns' => $columnNames,
            'ai_suggested_mappings' => $suggestedMappings,
            'total_rows' => $rowCount,
            'status' => ImportSession::STATUS_MAPPING,
        ]);

        Log::info('Import database analyzed', [
            'session_id' => $session->id,
            'source' => $session->source_table ?? 'custom_query',
            'columns' => count($columnNames),
            'rows' => $rowCount,
        ]);
    }

    public function processSession(ImportSession $session): void
    {
        $session->update([
            'status' => ImportSession::STATUS_PROCESSING,
            'started_at' => now(),
        ]);

        try {
            $mapping = $session->mapping;

            if (!$mapping) {
                throw new \Exception('Aucun mapping configuré pour cette session');
            }

            // Check if this is a database import or file import
            if ($session->isDatabaseImport()) {
                $this->processFromDatabase($session, $mapping->column_mappings);
            } else {
                $this->processFromFile($session, $mapping->column_mappings);
            }
        } catch (\Exception $e) {
            $session->update([
                'status' => ImportSession::STATUS_FAILED,
                'errors_summary' => ['processing_error' => $e->getMessage()],
            ]);

            throw $e;
        }
    }

    private function processFromFile(ImportSession $session, array $columnMappings): void
    {
        $filePath = Storage::path($session->file_path);
        $parsed = $this->parser->parseFile($filePath);

        $rowNumber = 0;
        foreach ($parsed['rows'] as $rawRow) {
            $rowNumber++;

            ImportRow::create([
                'import_session_id' => $session->id,
                'row_number' => $rowNumber,
                'raw_data' => $rawRow,
                'normalized_data' => null,
                'status' => ImportRow::STATUS_PENDING,
            ]);
        }

        $session->update(['total_rows' => $rowNumber]);

        Log::info('Import rows created from file', [
            'session_id' => $session->id,
            'total_rows' => $rowNumber,
        ]);
    }

    private function processFromDatabase(ImportSession $session, array $columnMappings): void
    {
        $connection = $session->databaseConnection;
        if (!$connection) {
            throw new \Exception('Connexion base de données non trouvée');
        }

        $config = $connection->getConnectionConfig();
        $batchSize = 100;
        $offset = 0;
        $rowNumber = 0;

        do {
            if ($session->source_table) {
                $result = $this->databaseConnector->fetchTableData(
                    $config,
                    $session->source_table,
                    $offset,
                    $batchSize
                );
                $rows = $result['rows'];
                $hasMore = $result['has_more'];
            } else {
                // For custom queries, we need to add OFFSET/LIMIT manually
                $query = rtrim($session->source_query, ';');
                $query .= " LIMIT {$batchSize} OFFSET {$offset}";
                $rows = $this->databaseConnector->executeQuery($config, $query, $batchSize + 1);
                $hasMore = count($rows) > $batchSize;
                $rows = array_slice($rows, 0, $batchSize);
            }

            foreach ($rows as $rawRow) {
                $rowNumber++;

                ImportRow::create([
                    'import_session_id' => $session->id,
                    'row_number' => $rowNumber,
                    'raw_data' => $rawRow,
                    'normalized_data' => null,
                    'status' => ImportRow::STATUS_PENDING,
                ]);
            }

            $offset += $batchSize;
        } while ($hasMore && count($rows) === $batchSize);

        $session->update(['total_rows' => $rowNumber]);

        Log::info('Import rows created from database', [
            'session_id' => $session->id,
            'total_rows' => $rowNumber,
        ]);
    }

    public function processBatch(ImportSession $session, int $offset, int $limit = 50): array
    {
        $mapping = $session->mapping;
        if (!$mapping) {
            throw new \Exception('Aucun mapping configuré');
        }

        $columnMappings = $mapping->column_mappings;

        // Query by row_number range instead of skip/take on pending rows
        // This ensures consistent batch processing even as rows change status
        $startRow = $offset + 1; // row_number is 1-indexed
        $endRow = $offset + $limit;

        $rows = ImportRow::where('import_session_id', $session->id)
            ->where('status', ImportRow::STATUS_PENDING)
            ->whereBetween('row_number', [$startRow, $endRow])
            ->orderBy('row_number')
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
                // Conjoint
                if (isset($data['conjoint']) && !empty($data['conjoint'])) {
                    $this->createOrUpdateConjoint($client, $data['conjoint']);
                }

                // Enfants
                if (isset($data['enfants']) && !empty($data['enfants'])) {
                    $this->enfantSyncService->syncEnfants($client, $data['enfants']);
                }

                // Santé / Mutuelle
                if (isset($data['_sante_souhaits']) && !empty($data['_sante_souhaits'])) {
                    $this->createOrUpdateSanteSouhait($client, $data['_sante_souhaits']);
                }

                // Prévoyance
                if (isset($data['_bae_prevoyance']) && !empty($data['_bae_prevoyance'])) {
                    $this->createOrUpdateBaePrevoyance($client, $data['_bae_prevoyance']);
                }

                // Retraite
                if (isset($data['_bae_retraite']) && !empty($data['_bae_retraite'])) {
                    $this->createOrUpdateBaeRetraite($client, $data['_bae_retraite']);
                }

                // Épargne globale
                if (isset($data['_bae_epargne']) && !empty($data['_bae_epargne'])) {
                    $this->createOrUpdateBaeEpargne($client, $data['_bae_epargne']);
                }

                // Revenus (multiple)
                if (isset($data['_client_revenu']) && !empty($data['_client_revenu'])) {
                    $this->createClientRevenu($client, $data['_client_revenu']);
                }

                // Actifs financiers (multiple)
                if (isset($data['_client_actif_financier']) && !empty($data['_client_actif_financier'])) {
                    $this->createClientActifFinancier($client, $data['_client_actif_financier']);
                }

                // Biens immobiliers (multiple)
                if (isset($data['_client_bien_immobilier']) && !empty($data['_client_bien_immobilier'])) {
                    $this->createClientBienImmobilier($client, $data['_client_bien_immobilier']);
                }

                // Passifs / Emprunts (multiple)
                if (isset($data['_client_passif']) && !empty($data['_client_passif'])) {
                    $this->createClientPassif($client, $data['_client_passif']);
                }

                // Autres épargnes (multiple)
                if (isset($data['_client_autre_epargne']) && !empty($data['_client_autre_epargne'])) {
                    $this->createClientAutreEpargne($client, $data['_client_autre_epargne']);
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
            // Required fields
            'team_id' => $teamId,
            'user_id' => $userId,
            'nom' => $data['nom'] ?? null,
            'prenom' => $data['prenom'] ?? null,

            // Identity fields
            'civilite' => $data['civilite'] ?? null,
            'nom_jeune_fille' => $data['nom_jeune_fille'] ?? null,

            // Birth information
            'date_naissance' => $data['date_naissance'] ?? null,
            'lieu_naissance' => $data['lieu_naissance'] ?? null,
            'nationalite' => $data['nationalite'] ?? null,

            // Contact information
            'email' => $data['email'] ?? null,
            'telephone' => $data['telephone'] ?? null,
            'adresse' => $data['adresse'] ?? null,
            'code_postal' => $data['code_postal'] ?? null,
            'ville' => $data['ville'] ?? null,
            'residence_fiscale' => $data['residence_fiscale'] ?? null,

            // Family situation
            'situation_matrimoniale' => $data['situation_matrimoniale'] ?? null,
            'date_situation_matrimoniale' => $data['date_situation_matrimoniale'] ?? null,

            // Professional information
            'profession' => $data['profession'] ?? null,
            'situation_actuelle' => $data['situation_actuelle'] ?? null,
            'date_evenement_professionnel' => $data['date_evenement_professionnel'] ?? null,
            'revenus_annuels' => $data['revenus_annuels'] ?? null,
            'chef_entreprise' => $data['chef_entreprise'] ?? null,
            'risques_professionnels' => $data['risques_professionnels'] ?? null,
            'details_risques_professionnels' => $data['details_risques_professionnels'] ?? null,

            // Health / Lifestyle
            'fumeur' => $data['fumeur'] ?? null,
            'activites_sportives' => $data['activites_sportives'] ?? null,
            'niveau_activites_sportives' => $data['niveau_activites_sportives'] ?? null,
            'details_activites_sportives' => $data['details_activites_sportives'] ?? null,
        ], fn($v) => $v !== null);

        return Client::create($clientData);
    }

    private function mergeWithExisting(int $clientId, array $data): Client
    {
        $client = Client::findOrFail($clientId);

        $updateData = [];
        $fieldsToMerge = [
            // Contact
            'email', 'telephone', 'adresse', 'code_postal', 'ville', 'residence_fiscale',
            // Identity
            'nom_jeune_fille', 'lieu_naissance', 'nationalite',
            // Family
            'situation_matrimoniale', 'date_situation_matrimoniale',
            // Professional
            'profession', 'situation_actuelle', 'date_evenement_professionnel',
            'revenus_annuels', 'chef_entreprise',
            'risques_professionnels', 'details_risques_professionnels',
            // Health / Lifestyle
            'fumeur', 'activites_sportives', 'niveau_activites_sportives', 'details_activites_sportives',
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

            // Identity
            'nom' => $conjointData['nom'] ?? null,
            'nom_jeune_fille' => $conjointData['nom_jeune_fille'] ?? null,
            'prenom' => $conjointData['prenom'] ?? null,

            // Birth information
            'date_naissance' => $conjointData['date_naissance'] ?? null,
            'lieu_naissance' => $conjointData['lieu_naissance'] ?? null,
            'nationalite' => $conjointData['nationalite'] ?? null,

            // Contact
            'telephone' => $conjointData['telephone'] ?? null,
            'email' => $conjointData['email'] ?? null,
            'adresse' => $conjointData['adresse'] ?? null,
            'code_postal' => $conjointData['code_postal'] ?? null,
            'ville' => $conjointData['ville'] ?? null,

            // Professional
            'profession' => $conjointData['profession'] ?? null,
            'situation_professionnelle' => $conjointData['situation_professionnelle'] ?? null,
            'situation_chomage' => $conjointData['situation_chomage'] ?? null,
            'statut' => $conjointData['statut'] ?? null,
            'chef_entreprise' => $conjointData['chef_entreprise'] ?? null,
            'travailleur_independant' => $conjointData['travailleur_independant'] ?? null,
            'situation_actuelle_statut' => $conjointData['situation_actuelle_statut'] ?? null,
            'date_evenement_professionnel' => $conjointData['date_evenement_professionnel'] ?? null,
            'risques_professionnels' => $conjointData['risques_professionnels'] ?? null,
            'details_risques_professionnels' => $conjointData['details_risques_professionnels'] ?? null,

            // Health / Lifestyle
            'fumeur' => $conjointData['fumeur'] ?? null,
            'activites_sportives' => $conjointData['activites_sportives'] ?? null,
            'niveau_activite_sportive' => $conjointData['niveau_activite_sportive'] ?? null,
            'details_activites_sportives' => $conjointData['details_activites_sportives'] ?? null,
            'km_parcourus_annuels' => $conjointData['km_parcourus_annuels'] ?? null,
        ], fn($v) => $v !== null);

        if ($conjoint) {
            $conjoint->update($data);
        } else {
            $client->conjoint()->create($data);
        }
    }

    private function createOrUpdateSanteSouhait(Client $client, array $santeData): void
    {
        $santeSouhait = $client->santeSouhait;

        $data = array_filter([
            'client_id' => $client->id,
            'contrat_en_place' => $this->normalizeImportValue($santeData['contrat_en_place'] ?? null),
            'budget_mensuel_maximum' => $this->normalizeImportValue($santeData['budget_mensuel_maximum'] ?? null),
            'niveau_hospitalisation' => $this->normalizeImportValue($santeData['niveau_hospitalisation'] ?? null),
            'niveau_chambre_particuliere' => $this->normalizeImportValue($santeData['niveau_chambre_particuliere'] ?? null),
            'niveau_medecin_generaliste' => $this->normalizeImportValue($santeData['niveau_medecin_generaliste'] ?? null),
            'niveau_analyses_imagerie' => $this->normalizeImportValue($santeData['niveau_analyses_imagerie'] ?? null),
            'niveau_auxiliaires_medicaux' => $this->normalizeImportValue($santeData['niveau_auxiliaires_medicaux'] ?? null),
            'niveau_pharmacie' => $this->normalizeImportValue($santeData['niveau_pharmacie'] ?? null),
            'niveau_dentaire' => $this->normalizeImportValue($santeData['niveau_dentaire'] ?? null),
            'niveau_optique' => $this->normalizeImportValue($santeData['niveau_optique'] ?? null),
            'niveau_protheses_auditives' => $this->normalizeImportValue($santeData['niveau_protheses_auditives'] ?? null),
        ], fn($v) => $v !== null);

        if (count($data) <= 1) {
            return; // Seulement client_id, pas de données utiles
        }

        if ($santeSouhait) {
            $santeSouhait->update($data);
        } else {
            $client->santeSouhait()->create($data);
        }
    }

    private function normalizeImportValue(mixed $value): mixed
    {
        if (is_string($value)) {
            $trimmed = trim($value);
            return $trimmed === '' ? null : $trimmed;
        }

        return $value;
    }

    private function normalizeNumericImportValue(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        // Éviter d'insérer des dates dans des champs numériques
        if (preg_match('/\b\d{1,2}[\/\.\-]\d{1,2}[\/\.\-]\d{2,4}\b/', $trimmed)) {
            return null;
        }

        $normalized = str_replace([' ', "\u{A0}"], '', $trimmed);
        $normalized = str_replace('%', '', $normalized);
        $normalized = str_replace(',', '.', $normalized);
        $normalized = preg_replace('/[^0-9\.\-]/', '', $normalized);

        if ($normalized === '' || $normalized === '-' || $normalized === '.') {
            return null;
        }

        if (!is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }

    private function normalizeIntegerImportValue(mixed $value): ?int
    {
        $numeric = $this->normalizeNumericImportValue($value);
        if ($numeric === null) {
            return null;
        }

        return (int) round($numeric);
    }

    private function normalizeDateImportValue(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $formats = ['d/m/Y', 'd.m.Y', 'd-m-Y', 'Y-m-d'];
        foreach ($formats as $format) {
            $date = \Carbon\Carbon::createFromFormat($format, $trimmed);
            if ($date !== false) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    private function createOrUpdateBaePrevoyance(Client $client, array $prevoyanceData): void
    {
        $baePrevoyance = $client->baePrevoyance;

        $data = array_filter([
            'client_id' => $client->id,
            'contrat_en_place' => $this->normalizeImportValue($prevoyanceData['contrat_en_place'] ?? null),
            'date_effet' => $this->normalizeDateImportValue($prevoyanceData['date_effet'] ?? null),
            'cotisations' => $this->normalizeNumericImportValue($prevoyanceData['cotisations'] ?? null),
            'souhaite_couverture_invalidite' => $this->normalizeImportValue($prevoyanceData['souhaite_couverture_invalidite'] ?? null),
            'revenu_a_garantir' => $this->normalizeNumericImportValue($prevoyanceData['revenu_a_garantir'] ?? null),
            'souhaite_couvrir_charges_professionnelles' => $this->normalizeImportValue($prevoyanceData['souhaite_couvrir_charges_professionnelles'] ?? null),
            'montant_annuel_charges_professionnelles' => $this->normalizeNumericImportValue($prevoyanceData['montant_annuel_charges_professionnelles'] ?? null),
            'garantir_totalite_charges_professionnelles' => $this->normalizeImportValue($prevoyanceData['garantir_totalite_charges_professionnelles'] ?? null),
            'montant_charges_professionnelles_a_garantir' => $this->normalizeNumericImportValue($prevoyanceData['montant_charges_professionnelles_a_garantir'] ?? null),
            'duree_indemnisation_souhaitee' => $this->normalizeImportValue($prevoyanceData['duree_indemnisation_souhaitee'] ?? null),
            'capital_deces_souhaite' => $this->normalizeNumericImportValue($prevoyanceData['capital_deces_souhaite'] ?? null),
            'garanties_obseques' => $this->normalizeNumericImportValue($prevoyanceData['garanties_obseques'] ?? null),
            'rente_enfants' => $this->normalizeNumericImportValue($prevoyanceData['rente_enfants'] ?? null),
            'rente_conjoint' => $this->normalizeNumericImportValue($prevoyanceData['rente_conjoint'] ?? null),
            'payeur' => $this->normalizeImportValue($prevoyanceData['payeur'] ?? null),
        ], fn($v) => $v !== null);

        if (count($data) <= 1) {
            return;
        }

        if ($baePrevoyance) {
            $baePrevoyance->update($data);
        } else {
            $client->baePrevoyance()->create($data);
        }
    }

    private function createOrUpdateBaeRetraite(Client $client, array $retraiteData): void
    {
        $baeRetraite = $client->baeRetraite;

        $data = array_filter([
            'client_id' => $client->id,
            'revenus_annuels' => $this->normalizeNumericImportValue($retraiteData['revenus_annuels'] ?? null),
            'revenus_annuels_foyer' => $this->normalizeNumericImportValue($retraiteData['revenus_annuels_foyer'] ?? null),
            'impot_revenu' => $this->normalizeNumericImportValue($retraiteData['impot_revenu'] ?? null),
            'nombre_parts_fiscales' => $this->normalizeNumericImportValue($retraiteData['nombre_parts_fiscales'] ?? null),
            'tmi' => $this->normalizeImportValue($retraiteData['tmi'] ?? null),
            'impot_paye_n_1' => $this->normalizeNumericImportValue($retraiteData['impot_paye_n_1'] ?? null),
            'age_depart_retraite' => $this->normalizeIntegerImportValue($retraiteData['age_depart_retraite'] ?? null),
            'age_depart_retraite_conjoint' => $this->normalizeIntegerImportValue($retraiteData['age_depart_retraite_conjoint'] ?? null),
            'pourcentage_revenu_a_maintenir' => $this->normalizeNumericImportValue($retraiteData['pourcentage_revenu_a_maintenir'] ?? null),
            'contrat_en_place' => $this->normalizeImportValue($retraiteData['contrat_en_place'] ?? null),
            'bilan_retraite_disponible' => $this->normalizeImportValue($retraiteData['bilan_retraite_disponible'] ?? null),
            'complementaire_retraite_mise_en_place' => $this->normalizeImportValue($retraiteData['complementaire_retraite_mise_en_place'] ?? null),
            'designation_etablissement' => $this->normalizeImportValue($retraiteData['designation_etablissement'] ?? null),
            'cotisations_annuelles' => $this->normalizeNumericImportValue($retraiteData['cotisations_annuelles'] ?? null),
            'titulaire' => $this->normalizeImportValue($retraiteData['titulaire'] ?? null),
        ], fn($v) => $v !== null);

        if (count($data) <= 1) {
            return;
        }

        if ($baeRetraite) {
            $baeRetraite->update($data);
        } else {
            $client->baeRetraite()->create($data);
        }
    }

    private function createOrUpdateBaeEpargne(Client $client, array $epargneData): void
    {
        $baeEpargne = $client->baeEpargne;

        $data = array_filter([
            'client_id' => $client->id,
            'epargne_disponible' => $this->normalizeImportValue($epargneData['epargne_disponible'] ?? null),
            'montant_epargne_disponible' => $this->normalizeNumericImportValue($epargneData['montant_epargne_disponible'] ?? null),
            'donation_realisee' => $this->normalizeImportValue($epargneData['donation_realisee'] ?? null),
            'donation_forme' => $this->normalizeImportValue($epargneData['donation_forme'] ?? null),
            'donation_date' => $this->normalizeDateImportValue($epargneData['donation_date'] ?? null),
            'donation_montant' => $this->normalizeNumericImportValue($epargneData['donation_montant'] ?? null),
            'donation_beneficiaires' => $this->normalizeImportValue($epargneData['donation_beneficiaires'] ?? null),
            'capacite_epargne_estimee' => $this->normalizeNumericImportValue($epargneData['capacite_epargne_estimee'] ?? null),
            'actifs_financiers_pourcentage' => $this->normalizeNumericImportValue($epargneData['actifs_financiers_pourcentage'] ?? null),
            'actifs_financiers_total' => $this->normalizeNumericImportValue($epargneData['actifs_financiers_total'] ?? null),
            'actifs_financiers_details' => $this->normalizeImportValue($epargneData['actifs_financiers_details'] ?? null),
            'actifs_immo_pourcentage' => $this->normalizeNumericImportValue($epargneData['actifs_immo_pourcentage'] ?? null),
            'actifs_immo_total' => $this->normalizeNumericImportValue($epargneData['actifs_immo_total'] ?? null),
            'actifs_immo_details' => $this->normalizeImportValue($epargneData['actifs_immo_details'] ?? null),
            'actifs_autres_pourcentage' => $this->normalizeNumericImportValue($epargneData['actifs_autres_pourcentage'] ?? null),
            'actifs_autres_total' => $this->normalizeNumericImportValue($epargneData['actifs_autres_total'] ?? null),
            'actifs_autres_details' => $this->normalizeImportValue($epargneData['actifs_autres_details'] ?? null),
            'passifs_total_emprunts' => $this->normalizeNumericImportValue($epargneData['passifs_total_emprunts'] ?? null),
            'passifs_details' => $this->normalizeImportValue($epargneData['passifs_details'] ?? null),
            'charges_totales' => $this->normalizeNumericImportValue($epargneData['charges_totales'] ?? null),
            'charges_details' => $this->normalizeImportValue($epargneData['charges_details'] ?? null),
            'situation_financiere_revenus_charges' => $this->normalizeImportValue($epargneData['situation_financiere_revenus_charges'] ?? null),
        ], fn($v) => $v !== null);

        if (count($data) <= 1) {
            return;
        }

        if ($baeEpargne) {
            $baeEpargne->update($data);
        } else {
            $client->baeEpargne()->create($data);
        }
    }

    private function createClientRevenu(Client $client, array $revenuData): void
    {
        $data = array_filter([
            'client_id' => $client->id,
            'nature' => $this->normalizeImportValue($revenuData['nature'] ?? null),
            'details' => $this->normalizeImportValue($revenuData['details'] ?? null),
            'periodicite' => $this->normalizeImportValue($revenuData['periodicite'] ?? null),
            'montant' => $this->normalizeNumericImportValue($revenuData['montant'] ?? null),
        ], fn($v) => $v !== null);

        if (count($data) <= 1) {
            return;
        }

        $client->revenus()->create($data);
    }

    private function createClientActifFinancier(Client $client, array $actifData): void
    {
        $data = array_filter([
            'client_id' => $client->id,
            'nature' => $this->normalizeImportValue($actifData['nature'] ?? null),
            'etablissement' => $this->normalizeImportValue($actifData['etablissement'] ?? null),
            'detenteur' => $this->normalizeImportValue($actifData['detenteur'] ?? null),
            'date_ouverture_souscription' => $this->normalizeDateImportValue($actifData['date_ouverture_souscription'] ?? null),
            'valeur_actuelle' => $this->normalizeNumericImportValue($actifData['valeur_actuelle'] ?? null),
        ], fn($v) => $v !== null);

        if (count($data) <= 1) {
            return;
        }

        $client->actifsFinanciers()->create($data);
    }

    private function createClientBienImmobilier(Client $client, array $bienData): void
    {
        $data = array_filter([
            'client_id' => $client->id,
            'designation' => $this->normalizeImportValue($bienData['designation'] ?? null),
            'detenteur' => $this->normalizeImportValue($bienData['detenteur'] ?? null),
            'forme_propriete' => $this->normalizeImportValue($bienData['forme_propriete'] ?? null),
            'valeur_actuelle_estimee' => $this->normalizeNumericImportValue($bienData['valeur_actuelle_estimee'] ?? null),
            'annee_acquisition' => $this->normalizeIntegerImportValue($bienData['annee_acquisition'] ?? null),
            'valeur_acquisition' => $this->normalizeNumericImportValue($bienData['valeur_acquisition'] ?? null),
        ], fn($v) => $v !== null);

        if (count($data) <= 1) {
            return;
        }

        $client->biensImmobiliers()->create($data);
    }

    private function createClientPassif(Client $client, array $passifData): void
    {
        $data = array_filter([
            'client_id' => $client->id,
            'nature' => $this->normalizeImportValue($passifData['nature'] ?? null),
            'preteur' => $this->normalizeImportValue($passifData['preteur'] ?? null),
            'periodicite' => $this->normalizeImportValue($passifData['periodicite'] ?? null),
            'montant_remboursement' => $this->normalizeNumericImportValue($passifData['montant_remboursement'] ?? null),
            'capital_restant_du' => $this->normalizeNumericImportValue($passifData['capital_restant_du'] ?? null),
            'duree_restante' => $this->normalizeIntegerImportValue($passifData['duree_restante'] ?? null),
        ], fn($v) => $v !== null);

        if (count($data) <= 1) {
            return;
        }

        $client->passifs()->create($data);
    }

    private function createClientAutreEpargne(Client $client, array $epargneData): void
    {
        $data = array_filter([
            'client_id' => $client->id,
            'designation' => $this->normalizeImportValue($epargneData['designation'] ?? null),
            'detenteur' => $this->normalizeImportValue($epargneData['detenteur'] ?? null),
            'valeur' => $this->normalizeNumericImportValue($epargneData['valeur'] ?? null),
        ], fn($v) => $v !== null);

        if (count($data) <= 1) {
            return;
        }

        $client->autresEpargnes()->create($data);
    }
}
