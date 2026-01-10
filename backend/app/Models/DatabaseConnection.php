<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class DatabaseConnection extends Model
{
    use HasFactory;

    public const DRIVER_MYSQL = 'mysql';
    public const DRIVER_PGSQL = 'pgsql';
    public const DRIVER_SQLITE = 'sqlite';
    public const DRIVER_SQLSRV = 'sqlsrv';

    protected $fillable = [
        'team_id',
        'created_by',
        'name',
        'driver',
        'host',
        'port',
        'database',
        'username',
        'password_encrypted',
        'schema',
        'options',
        'last_tested_at',
        'is_active',
        'is_ephemeral',
        'purpose',
        'data_category',
    ];

    protected $casts = [
        'port' => 'integer',
        'options' => 'array',
        'last_tested_at' => 'datetime',
        'is_active' => 'boolean',
        'is_ephemeral' => 'boolean',
    ];

    protected $hidden = [
        'password_encrypted',
    ];

    /**
     * Get the team that owns this connection
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user who created this connection
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get import sessions using this connection
     */
    public function importSessions(): HasMany
    {
        return $this->hasMany(ImportSession::class);
    }

    /**
     * Set the password (encrypted)
     */
    public function setPasswordAttribute(?string $value): void
    {
        if ($value !== null) {
            $this->attributes['password_encrypted'] = Crypt::encryptString($value);
        } else {
            $this->attributes['password_encrypted'] = null;
        }
    }

    /**
     * Get the decrypted password
     */
    public function getPassword(): ?string
    {
        if ($this->password_encrypted === null) {
            return null;
        }

        try {
            return Crypt::decryptString($this->password_encrypted);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get connection configuration array for DatabaseConnectorService
     */
    public function getConnectionConfig(): array
    {
        $config = [
            'driver' => $this->driver,
            'database' => $this->database,
        ];

        if ($this->host) {
            $config['host'] = $this->host;
        }

        if ($this->port) {
            $config['port'] = $this->port;
        }

        if ($this->username) {
            $config['username'] = $this->username;
        }

        $password = $this->getPassword();
        if ($password !== null) {
            $config['password'] = $password;
        }

        if ($this->schema) {
            $config['schema'] = $this->schema;
        }

        if ($this->options) {
            $config = array_merge($config, $this->options);
        }

        return $config;
    }

    /**
     * Get available drivers
     */
    public static function getAvailableDrivers(): array
    {
        return [
            self::DRIVER_MYSQL => 'MySQL / MariaDB',
            self::DRIVER_PGSQL => 'PostgreSQL',
            self::DRIVER_SQLITE => 'SQLite',
            self::DRIVER_SQLSRV => 'SQL Server',
        ];
    }

    /**
     * Get default port for a driver
     */
    public static function getDefaultPort(string $driver): ?int
    {
        return match ($driver) {
            self::DRIVER_MYSQL => 3306,
            self::DRIVER_PGSQL => 5432,
            self::DRIVER_SQLSRV => 1433,
            default => null,
        };
    }

    /**
     * Scope to active connections
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to team connections
     */
    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }
}
