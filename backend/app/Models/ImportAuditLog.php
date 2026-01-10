<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

class ImportAuditLog extends Model
{
    // Actions
    public const ACTION_UPLOAD = 'upload';
    public const ACTION_CONNECT = 'connect';
    public const ACTION_IMPORT = 'import';
    public const ACTION_EXPORT = 'export';
    public const ACTION_DELETE = 'delete';
    public const ACTION_VIEW = 'view';
    public const ACTION_CONSENT = 'consent';
    public const ACTION_CONNECTION_TEST = 'connection_test';
    public const ACTION_CONNECTION_CREATE = 'connection_create';
    public const ACTION_CONNECTION_DELETE = 'connection_delete';

    // Resource types
    public const RESOURCE_SESSION = 'import_session';
    public const RESOURCE_CONNECTION = 'database_connection';
    public const RESOURCE_CLIENT = 'client';
    public const RESOURCE_MAPPING = 'import_mapping';

    // Legal bases (RGPD Article 6)
    public const LEGAL_BASIS_CONSENT = 'consent';
    public const LEGAL_BASIS_CONTRACT = 'contract';
    public const LEGAL_BASIS_LEGAL_OBLIGATION = 'legal_obligation';
    public const LEGAL_BASIS_VITAL_INTERESTS = 'vital_interests';
    public const LEGAL_BASIS_PUBLIC_TASK = 'public_task';
    public const LEGAL_BASIS_LEGITIMATE_INTEREST = 'legitimate_interest';

    protected $fillable = [
        'team_id',
        'user_id',
        'import_session_id',
        'database_connection_id',
        'action',
        'resource_type',
        'resource_id',
        'legal_basis',
        'legal_basis_details',
        'consent_confirmed',
        'consent_timestamp',
        'ip_address',
        'user_agent',
        'metadata',
        'success',
        'error_message',
        'records_affected',
    ];

    protected $casts = [
        'consent_confirmed' => 'boolean',
        'consent_timestamp' => 'datetime',
        'metadata' => 'array',
        'success' => 'boolean',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function importSession(): BelongsTo
    {
        return $this->belongsTo(ImportSession::class);
    }

    public function databaseConnection(): BelongsTo
    {
        return $this->belongsTo(DatabaseConnection::class);
    }

    /**
     * Log an action with request context
     */
    public static function logAction(
        string $action,
        string $resourceType,
        ?int $resourceId = null,
        array $options = [],
        ?Request $request = null
    ): self {
        $user = $request?->user() ?? auth()->user();

        return self::create([
            'team_id' => $user?->current_team_id ?? $options['team_id'] ?? null,
            'user_id' => $user?->id ?? $options['user_id'] ?? null,
            'import_session_id' => $options['import_session_id'] ?? null,
            'database_connection_id' => $options['database_connection_id'] ?? null,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'legal_basis' => $options['legal_basis'] ?? null,
            'legal_basis_details' => $options['legal_basis_details'] ?? null,
            'consent_confirmed' => $options['consent_confirmed'] ?? false,
            'consent_timestamp' => $options['consent_confirmed'] ? now() : null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'metadata' => $options['metadata'] ?? null,
            'success' => $options['success'] ?? true,
            'error_message' => $options['error_message'] ?? null,
            'records_affected' => $options['records_affected'] ?? 0,
        ]);
    }

    /**
     * Get available legal bases with French labels
     */
    public static function getLegalBasesLabels(): array
    {
        return [
            self::LEGAL_BASIS_CONSENT => 'Consentement de la personne',
            self::LEGAL_BASIS_CONTRACT => 'Exécution d\'un contrat',
            self::LEGAL_BASIS_LEGAL_OBLIGATION => 'Obligation légale',
            self::LEGAL_BASIS_VITAL_INTERESTS => 'Intérêts vitaux',
            self::LEGAL_BASIS_PUBLIC_TASK => 'Mission d\'intérêt public',
            self::LEGAL_BASIS_LEGITIMATE_INTEREST => 'Intérêt légitime',
        ];
    }

    /**
     * Scope to team
     */
    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    /**
     * Scope to date range
     */
    public function scopeDateRange($query, $from, $to)
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }
}
