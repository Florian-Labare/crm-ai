<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Service d'audit logging
 *
 * Enregistre toutes les actions sensibles pour la conformité et la traçabilité
 */
class AuditService
{
    private ?string $requestId = null;

    public function __construct()
    {
        $this->requestId = (string) Str::uuid();
    }

    /**
     * Enregistre une action dans le journal d'audit
     */
    public function log(
        string $action,
        string $description,
        ?Model $resource = null,
        string $category = 'general',
        string $level = 'info',
        ?array $oldValues = null,
        ?array $newValues = null
    ): AuditLog {
        $user = auth()->user();

        $log = AuditLog::create([
            'team_id' => $user?->currentTeam()?->id,
            'user_id' => $user?->id,
            'action' => $action,
            'resource_type' => $resource ? get_class($resource) : null,
            'resource_id' => $resource?->id,
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => substr(request()->userAgent() ?? '', 0, 255),
            'request_id' => $this->requestId,
            'category' => $category,
            'level' => $level,
        ]);

        // Logger aussi dans les logs Laravel pour les actions critiques
        if ($level === 'critical') {
            Log::warning('[AUDIT] Action critique', [
                'audit_id' => $log->id,
                'action' => $action,
                'description' => $description,
                'user_id' => $user?->id,
                'resource' => $resource ? get_class($resource) . '#' . $resource->id : null
            ]);
        }

        return $log;
    }

    /**
     * Raccourcis pour les actions courantes
     */
    public function logCreate(Model $resource, string $description, string $category = 'general'): AuditLog
    {
        return $this->log('create', $description, $resource, $category, 'info', null, $resource->toArray());
    }

    public function logUpdate(Model $resource, string $description, array $oldValues, string $category = 'general'): AuditLog
    {
        return $this->log('update', $description, $resource, $category, 'info', $oldValues, $resource->toArray());
    }

    public function logDelete(Model $resource, string $description, string $category = 'general', string $level = 'warning'): AuditLog
    {
        return $this->log('delete', $description, $resource, $category, $level, $resource->toArray(), null);
    }

    public function logAccess(Model $resource, string $description, string $category = 'general'): AuditLog
    {
        return $this->log('access', $description, $resource, $category, 'info');
    }

    public function logDownload(Model $resource, string $description, string $category = 'general'): AuditLog
    {
        return $this->log('download', $description, $resource, $category, 'info');
    }

    public function logExport(string $description, string $category = 'general', ?Model $resource = null): AuditLog
    {
        return $this->log('export', $description, $resource, $category, 'info');
    }

    /**
     * Actions audio spécifiques
     */
    public function logAudioUpload(Model $audioRecord): AuditLog
    {
        return $this->log(
            'upload',
            "Audio uploadé pour traitement",
            $audioRecord,
            'audio',
            'info'
        );
    }

    public function logAudioDelete(Model $audioRecord): AuditLog
    {
        return $this->log(
            'delete',
            "Enregistrement audio supprimé",
            $audioRecord,
            'audio',
            'warning',
            ['path' => $audioRecord->path, 'client_id' => $audioRecord->client_id]
        );
    }

    public function logSpeakerCorrection(Model $audioRecord, array $corrections): AuditLog
    {
        return $this->log(
            'update',
            "Correction des speakers appliquée",
            $audioRecord,
            'audio',
            'info',
            $audioRecord->getOriginal('speaker_corrections'),
            $corrections
        );
    }

    /**
     * Actions client spécifiques
     */
    public function logClientDelete(Model $client): AuditLog
    {
        return $this->log(
            'delete',
            "Client supprimé (RGPD): {$client->prenom} {$client->nom}",
            $client,
            'rgpd',
            'critical',
            [
                'nom' => $client->nom,
                'prenom' => $client->prenom,
                'email' => $client->email,
                'audio_records_count' => $client->audioRecords()->count()
            ]
        );
    }

    /**
     * Actions d'authentification
     */
    public function logLogin(): AuditLog
    {
        return $this->log('login', 'Connexion réussie', null, 'auth', 'info');
    }

    public function logLogout(): AuditLog
    {
        return $this->log('logout', 'Déconnexion', null, 'auth', 'info');
    }

    public function logFailedLogin(string $email): AuditLog
    {
        $log = AuditLog::create([
            'team_id' => null,
            'user_id' => null,
            'action' => 'login_failed',
            'resource_type' => null,
            'resource_id' => null,
            'description' => "Tentative de connexion échouée pour: {$email}",
            'old_values' => null,
            'new_values' => ['email' => $email],
            'ip_address' => request()->ip(),
            'user_agent' => substr(request()->userAgent() ?? '', 0, 255),
            'request_id' => $this->requestId,
            'category' => 'auth',
            'level' => 'warning',
        ]);

        return $log;
    }

    /**
     * Récupère le request ID courant (pour traçabilité)
     */
    public function getRequestId(): string
    {
        return $this->requestId;
    }
}
