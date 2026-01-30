<?php

namespace App\Services\Import;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Connection;
use PDO;
use PDOException;

class DatabaseConnectorService
{
    /**
     * Supported database drivers
     */
    private const SUPPORTED_DRIVERS = ['mysql', 'pgsql', 'sqlite', 'sqlsrv'];

    /**
     * Create a temporary database connection
     */
    public function createConnection(array $config): Connection
    {
        $driver = $config['driver'] ?? 'mysql';

        if (!in_array($driver, self::SUPPORTED_DRIVERS)) {
            throw new \InvalidArgumentException(
                "Driver non supporté: {$driver}. Drivers supportés: " . implode(', ', self::SUPPORTED_DRIVERS)
            );
        }

        $connectionName = 'import_temp_' . uniqid();

        config(["database.connections.{$connectionName}" => $this->buildConnectionConfig($config)]);

        return DB::connection($connectionName);
    }

    /**
     * Test a database connection
     */
    public function testConnection(array $config): array
    {
        try {
            $connection = $this->createConnection($config);
            $connection->getPdo();

            return [
                'success' => true,
                'message' => 'Connexion réussie',
            ];
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Échec de la connexion: ' . $this->sanitizeErrorMessage($e->getMessage()),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * List available tables in the database
     */
    public function listTables(array $config): array
    {
        $connection = $this->createConnection($config);
        $driver = $config['driver'] ?? 'mysql';

        $tables = match ($driver) {
            'mysql' => $this->listMysqlTables($connection),
            'pgsql' => $this->listPostgresTables($connection),
            'sqlite' => $this->listSqliteTables($connection),
            'sqlsrv' => $this->listSqlServerTables($connection),
            default => [],
        };

        return $tables;
    }

    /**
     * Get table columns and their types
     */
    public function getTableColumns(array $config, string $tableName): array
    {
        $connection = $this->createConnection($config);
        $driver = $config['driver'] ?? 'mysql';

        return match ($driver) {
            'mysql' => $this->getMysqlColumns($connection, $tableName),
            'pgsql' => $this->getPostgresColumns($connection, $tableName),
            'sqlite' => $this->getSqliteColumns($connection, $tableName),
            'sqlsrv' => $this->getSqlServerColumns($connection, $tableName),
            default => [],
        };
    }

    /**
     * Get sample data from a table
     */
    public function getSampleData(array $config, string $tableName, int $limit = 10): array
    {
        $connection = $this->createConnection($config);

        // Validate table name to prevent SQL injection
        if (!$this->isValidTableName($tableName)) {
            throw new \InvalidArgumentException('Nom de table invalide');
        }

        $rows = $connection->table($tableName)->limit($limit)->get();

        return $rows->map(fn($row) => (array) $row)->toArray();
    }

    /**
     * Get row count for a table
     */
    public function getTableRowCount(array $config, string $tableName): int
    {
        $connection = $this->createConnection($config);

        if (!$this->isValidTableName($tableName)) {
            throw new \InvalidArgumentException('Nom de table invalide');
        }

        return $connection->table($tableName)->count();
    }

    /**
     * Fetch data from a table in chunks
     */
    public function fetchTableData(array $config, string $tableName, int $offset = 0, int $limit = 100): array
    {
        $connection = $this->createConnection($config);

        if (!$this->isValidTableName($tableName)) {
            throw new \InvalidArgumentException('Nom de table invalide');
        }

        $rows = $connection->table($tableName)
            ->offset($offset)
            ->limit($limit)
            ->get();

        return [
            'rows' => $rows->map(fn($row) => (array) $row)->toArray(),
            'offset' => $offset,
            'limit' => $limit,
            'has_more' => $rows->count() === $limit,
        ];
    }

    /**
     * Execute a custom SQL query (SELECT only)
     */
    public function executeQuery(array $config, string $query, int $limit = 1000): array
    {
        // Security: Only allow SELECT statements
        $normalizedQuery = strtoupper(trim($query));
        if (!str_starts_with($normalizedQuery, 'SELECT')) {
            throw new \InvalidArgumentException('Seules les requêtes SELECT sont autorisées');
        }

        // Prevent dangerous operations
        $dangerousKeywords = ['INSERT', 'UPDATE', 'DELETE', 'DROP', 'CREATE', 'ALTER', 'TRUNCATE', 'EXEC', 'EXECUTE'];
        foreach ($dangerousKeywords as $keyword) {
            if (str_contains($normalizedQuery, $keyword)) {
                throw new \InvalidArgumentException("Opération non autorisée: {$keyword}");
            }
        }

        $connection = $this->createConnection($config);

        // Add LIMIT if not present
        if (!str_contains($normalizedQuery, 'LIMIT')) {
            $query = rtrim($query, ';') . " LIMIT {$limit}";
        }

        $results = $connection->select($query);

        return array_map(fn($row) => (array) $row, $results);
    }

    /**
     * Build connection configuration array
     */
    private function buildConnectionConfig(array $config): array
    {
        $driver = $config['driver'] ?? 'mysql';

        $baseConfig = [
            'driver' => $driver,
            'charset' => $config['charset'] ?? 'utf8mb4',
            'collation' => $config['collation'] ?? 'utf8mb4_unicode_ci',
            'prefix' => $config['prefix'] ?? '',
            'strict' => true,
        ];

        return match ($driver) {
            'mysql' => array_merge($baseConfig, [
                'host' => $config['host'] ?? '127.0.0.1',
                'port' => $config['port'] ?? 3306,
                'database' => $config['database'],
                'username' => $config['username'],
                'password' => $config['password'] ?? '',
            ]),
            'pgsql' => array_merge($baseConfig, [
                'host' => $config['host'] ?? '127.0.0.1',
                'port' => $config['port'] ?? 5432,
                'database' => $config['database'],
                'username' => $config['username'],
                'password' => $config['password'] ?? '',
                'schema' => $config['schema'] ?? 'public',
            ]),
            'sqlite' => [
                'driver' => 'sqlite',
                'database' => $config['database'],
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'sqlsrv' => array_merge($baseConfig, [
                'host' => $config['host'] ?? '127.0.0.1',
                'port' => $config['port'] ?? 1433,
                'database' => $config['database'],
                'username' => $config['username'],
                'password' => $config['password'] ?? '',
            ]),
            default => throw new \InvalidArgumentException("Driver non supporté: {$driver}"),
        };
    }

    /**
     * List MySQL tables
     */
    private function listMysqlTables(Connection $connection): array
    {
        $results = $connection->select('SHOW TABLES');
        $key = 'Tables_in_' . $connection->getDatabaseName();

        return array_map(fn($row) => (array) $row[$key] ?? array_values((array) $row)[0], $results);
    }

    /**
     * List PostgreSQL tables
     */
    private function listPostgresTables(Connection $connection): array
    {
        $results = $connection->select(
            "SELECT tablename FROM pg_tables WHERE schemaname = 'public'"
        );

        return array_map(fn($row) => $row->tablename, $results);
    }

    /**
     * List SQLite tables
     */
    private function listSqliteTables(Connection $connection): array
    {
        $results = $connection->select(
            "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"
        );

        return array_map(fn($row) => $row->name, $results);
    }

    /**
     * List SQL Server tables
     */
    private function listSqlServerTables(Connection $connection): array
    {
        $results = $connection->select(
            "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE'"
        );

        return array_map(fn($row) => $row->TABLE_NAME, $results);
    }

    /**
     * Get MySQL column information
     */
    private function getMysqlColumns(Connection $connection, string $tableName): array
    {
        $results = $connection->select("DESCRIBE `{$tableName}`");

        return array_map(fn($row) => [
            'name' => $row->Field,
            'type' => $row->Type,
            'nullable' => $row->Null === 'YES',
            'key' => $row->Key,
            'default' => $row->Default,
        ], $results);
    }

    /**
     * Get PostgreSQL column information
     */
    private function getPostgresColumns(Connection $connection, string $tableName): array
    {
        $results = $connection->select("
            SELECT column_name, data_type, is_nullable, column_default
            FROM information_schema.columns
            WHERE table_name = ?
            ORDER BY ordinal_position
        ", [$tableName]);

        return array_map(fn($row) => [
            'name' => $row->column_name,
            'type' => $row->data_type,
            'nullable' => $row->is_nullable === 'YES',
            'default' => $row->column_default,
        ], $results);
    }

    /**
     * Get SQLite column information
     */
    private function getSqliteColumns(Connection $connection, string $tableName): array
    {
        $results = $connection->select("PRAGMA table_info(`{$tableName}`)");

        return array_map(fn($row) => [
            'name' => $row->name,
            'type' => $row->type,
            'nullable' => $row->notnull === 0,
            'default' => $row->dflt_value,
            'primary_key' => $row->pk === 1,
        ], $results);
    }

    /**
     * Get SQL Server column information
     */
    private function getSqlServerColumns(Connection $connection, string $tableName): array
    {
        $results = $connection->select("
            SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_NAME = ?
            ORDER BY ORDINAL_POSITION
        ", [$tableName]);

        return array_map(fn($row) => [
            'name' => $row->COLUMN_NAME,
            'type' => $row->DATA_TYPE,
            'nullable' => $row->IS_NULLABLE === 'YES',
            'default' => $row->COLUMN_DEFAULT,
        ], $results);
    }

    /**
     * Validate table name to prevent SQL injection
     */
    private function isValidTableName(string $tableName): bool
    {
        return preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $tableName) === 1;
    }

    /**
     * Sanitize error messages to hide sensitive information
     */
    private function sanitizeErrorMessage(string $message): string
    {
        // Remove potential password or connection string info
        $message = preg_replace('/password[=:][^\s;]+/i', 'password=***', $message);
        $message = preg_replace('/pwd[=:][^\s;]+/i', 'pwd=***', $message);

        return $message;
    }

    /**
     * Get supported drivers
     */
    public function getSupportedDrivers(): array
    {
        return self::SUPPORTED_DRIVERS;
    }

    /**
     * Clean up temporary connection
     */
    public function cleanupConnection(string $connectionName): void
    {
        if (str_starts_with($connectionName, 'import_temp_')) {
            DB::purge($connectionName);
        }
    }
}
