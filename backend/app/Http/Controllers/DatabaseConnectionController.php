<?php

namespace App\Http\Controllers;

use App\Jobs\AnalyzeImportFileJob;
use App\Models\DatabaseConnection;
use App\Models\ImportSession;
use App\Services\Import\DatabaseConnectorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DatabaseConnectionController extends Controller
{
    public function __construct(
        private DatabaseConnectorService $connector
    ) {
    }

    /**
     * List database connections for the team
     */
    public function index(Request $request): JsonResponse
    {
        $teamId = $request->user()->current_team_id;

        $connections = DatabaseConnection::forTeam($teamId)
            ->with('creator:id,name')
            ->orderBy('name')
            ->get()
            ->map(function ($conn) {
                return [
                    'id' => $conn->id,
                    'name' => $conn->name,
                    'driver' => $conn->driver,
                    'host' => $conn->host,
                    'port' => $conn->port,
                    'database' => $conn->database,
                    'username' => $conn->username,
                    'schema' => $conn->schema,
                    'is_active' => $conn->is_active,
                    'last_tested_at' => $conn->last_tested_at,
                    'created_by' => $conn->creator?->name,
                    'created_at' => $conn->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $connections,
        ]);
    }

    /**
     * Get available drivers
     */
    public function drivers(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => DatabaseConnection::getAvailableDrivers(),
        ]);
    }

    /**
     * Create a new database connection
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'driver' => ['required', Rule::in($this->connector->getSupportedDrivers())],
            'host' => 'nullable|string|max:255',
            'port' => 'nullable|integer|min:1|max:65535',
            'database' => 'required|string|max:255',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'schema' => 'nullable|string|max:255',
            'options' => 'nullable|array',
        ]);

        $connection = DatabaseConnection::create([
            'team_id' => $request->user()->current_team_id,
            'created_by' => $request->user()->id,
            'name' => $validated['name'],
            'driver' => $validated['driver'],
            'host' => $validated['host'] ?? null,
            'port' => $validated['port'] ?? DatabaseConnection::getDefaultPort($validated['driver']),
            'database' => $validated['database'],
            'username' => $validated['username'] ?? null,
            'schema' => $validated['schema'] ?? null,
            'options' => $validated['options'] ?? null,
        ]);

        // Set password separately (uses encryption)
        if (!empty($validated['password'])) {
            $connection->password = $validated['password'];
            $connection->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Connexion créée',
            'data' => $connection,
        ], 201);
    }

    /**
     * Get a specific connection
     */
    public function show(DatabaseConnection $databaseConnection): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $databaseConnection->id,
                'name' => $databaseConnection->name,
                'driver' => $databaseConnection->driver,
                'host' => $databaseConnection->host,
                'port' => $databaseConnection->port,
                'database' => $databaseConnection->database,
                'username' => $databaseConnection->username,
                'schema' => $databaseConnection->schema,
                'options' => $databaseConnection->options,
                'is_active' => $databaseConnection->is_active,
                'last_tested_at' => $databaseConnection->last_tested_at,
            ],
        ]);
    }

    /**
     * Update a database connection
     */
    public function update(Request $request, DatabaseConnection $databaseConnection): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'driver' => ['sometimes', Rule::in($this->connector->getSupportedDrivers())],
            'host' => 'nullable|string|max:255',
            'port' => 'nullable|integer|min:1|max:65535',
            'database' => 'sometimes|string|max:255',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'schema' => 'nullable|string|max:255',
            'options' => 'nullable|array',
            'is_active' => 'sometimes|boolean',
        ]);

        $databaseConnection->fill(collect($validated)->except(['password'])->toArray());

        if (array_key_exists('password', $validated)) {
            $databaseConnection->password = $validated['password'];
        }

        $databaseConnection->save();

        return response()->json([
            'success' => true,
            'message' => 'Connexion mise à jour',
            'data' => $databaseConnection,
        ]);
    }

    /**
     * Delete a database connection
     */
    public function destroy(DatabaseConnection $databaseConnection): JsonResponse
    {
        // Check if connection is used by any import sessions
        $usedCount = ImportSession::where('database_connection_id', $databaseConnection->id)->count();
        if ($usedCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "Cette connexion est utilisée par {$usedCount} session(s) d'import",
            ], 422);
        }

        $databaseConnection->delete();

        return response()->json([
            'success' => true,
            'message' => 'Connexion supprimée',
        ]);
    }

    /**
     * Test a database connection
     */
    public function test(Request $request, ?DatabaseConnection $databaseConnection = null): JsonResponse
    {
        // If testing a saved connection
        if ($databaseConnection) {
            $config = $databaseConnection->getConnectionConfig();
        } else {
            // If testing new credentials
            $validated = $request->validate([
                'driver' => ['required', Rule::in($this->connector->getSupportedDrivers())],
                'host' => 'nullable|string|max:255',
                'port' => 'nullable|integer|min:1|max:65535',
                'database' => 'required|string|max:255',
                'username' => 'nullable|string|max:255',
                'password' => 'nullable|string|max:255',
                'schema' => 'nullable|string|max:255',
            ]);

            $config = [
                'driver' => $validated['driver'],
                'host' => $validated['host'] ?? null,
                'port' => $validated['port'] ?? DatabaseConnection::getDefaultPort($validated['driver']),
                'database' => $validated['database'],
                'username' => $validated['username'] ?? null,
                'password' => $validated['password'] ?? null,
                'schema' => $validated['schema'] ?? null,
            ];
        }

        $result = $this->connector->testConnection(array_filter($config));

        // Update last_tested_at if successful and testing a saved connection
        if ($result['success'] && $databaseConnection) {
            $databaseConnection->update(['last_tested_at' => now()]);
        }

        return response()->json($result);
    }

    /**
     * List tables in a database connection
     */
    public function tables(DatabaseConnection $databaseConnection): JsonResponse
    {
        try {
            $tables = $this->connector->listTables($databaseConnection->getConnectionConfig());

            return response()->json([
                'success' => true,
                'data' => $tables,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des tables: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get columns for a specific table
     */
    public function columns(DatabaseConnection $databaseConnection, string $table): JsonResponse
    {
        try {
            $columns = $this->connector->getTableColumns(
                $databaseConnection->getConnectionConfig(),
                $table
            );

            return response()->json([
                'success' => true,
                'data' => $columns,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des colonnes: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Preview data from a table
     */
    public function preview(Request $request, DatabaseConnection $databaseConnection): JsonResponse
    {
        $validated = $request->validate([
            'table' => 'required_without:query|string|max:255',
            'query' => 'required_without:table|string|max:5000',
            'limit' => 'integer|min:1|max:100',
        ]);

        try {
            if (!empty($validated['table'])) {
                $data = $this->connector->getSampleData(
                    $databaseConnection->getConnectionConfig(),
                    $validated['table'],
                    $validated['limit'] ?? 10
                );
                $rowCount = $this->connector->getTableRowCount(
                    $databaseConnection->getConnectionConfig(),
                    $validated['table']
                );
            } else {
                $data = $this->connector->executeQuery(
                    $databaseConnection->getConnectionConfig(),
                    $validated['query'],
                    $validated['limit'] ?? 100
                );
                $rowCount = count($data);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'rows' => $data,
                    'total_count' => $rowCount,
                    'columns' => !empty($data) ? array_keys($data[0]) : [],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la prévisualisation: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Create an import session from a database connection
     */
    public function createImportSession(Request $request, DatabaseConnection $databaseConnection): JsonResponse
    {
        $validated = $request->validate([
            'table' => 'required_without:query|string|max:255',
            'query' => 'required_without:table|string|max:5000',
            'import_mapping_id' => 'nullable|exists:import_mappings,id',
        ]);

        try {
            // Get row count
            if (!empty($validated['table'])) {
                $rowCount = $this->connector->getTableRowCount(
                    $databaseConnection->getConnectionConfig(),
                    $validated['table']
                );
                $sourceName = "Table: {$validated['table']}";
            } else {
                // For custom queries, we'll count during processing
                $rowCount = 0;
                $sourceName = "Requête personnalisée";
            }

            $session = ImportSession::create([
                'team_id' => $request->user()->current_team_id,
                'user_id' => $request->user()->id,
                'database_connection_id' => $databaseConnection->id,
                'source_table' => $validated['table'] ?? null,
                'source_query' => $validated['query'] ?? null,
                'import_mapping_id' => $validated['import_mapping_id'] ?? null,
                'original_filename' => $sourceName,
                'status' => ImportSession::STATUS_PENDING,
                'total_rows' => $rowCount,
            ]);

            // Dispatch analysis job
            AnalyzeImportFileJob::dispatch($session);

            return response()->json([
                'success' => true,
                'message' => 'Session d\'import créée, analyse en cours',
                'data' => $session,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la session: ' . $e->getMessage(),
            ], 422);
        }
    }
}
