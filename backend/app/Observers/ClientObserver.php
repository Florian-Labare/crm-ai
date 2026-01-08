<?php

namespace App\Observers;

use App\Models\AudioRecord;
use App\Models\Client;
use App\Models\DiarizationLog;
use App\Models\RecordingSession;
use App\Services\AuditService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Observer pour le modèle Client
 *
 * Gère notamment la suppression en cascade des données audio
 * pour la conformité RGPD (droit à l'effacement)
 */
class ClientObserver
{
    public function __construct(
        private readonly AuditService $auditService
    ) {
    }

    /**
     * Handle the Client "deleting" event.
     * Appelé AVANT la suppression effective du client
     */
    public function deleting(Client $client): void
    {
        // Audit RGPD : enregistrer la suppression du client
        $this->auditService->logClientDelete($client);
        Log::info('[CLIENT OBSERVER] Suppression en cascade initiée', [
            'client_id' => $client->id,
            'client_name' => "{$client->prenom} {$client->nom}",
            'team_id' => $client->team_id
        ]);

        // 1. Supprimer les enregistrements audio et leurs fichiers
        $this->deleteAudioRecords($client);

        // 2. Supprimer les sessions d'enregistrement et leurs fichiers
        $this->deleteRecordingSessions($client);

        Log::info('[CLIENT OBSERVER] Suppression en cascade terminée', [
            'client_id' => $client->id
        ]);
    }

    /**
     * Supprime tous les enregistrements audio d'un client
     */
    private function deleteAudioRecords(Client $client): void
    {
        $audioRecords = AudioRecord::withoutGlobalScopes()
            ->where('client_id', $client->id)
            ->get();

        $deletedCount = 0;
        $freedBytes = 0;

        foreach ($audioRecords as $record) {
            // Supprimer le fichier audio
            if ($record->path && Storage::disk('public')->exists($record->path)) {
                $size = Storage::disk('public')->size($record->path);
                Storage::disk('public')->delete($record->path);
                $freedBytes += $size;
            }

            // Supprimer les logs de diarisation
            DiarizationLog::where('audio_record_id', $record->id)->delete();

            // Nettoyer les fichiers temporaires
            $this->cleanupTempFiles($record->id);

            // Supprimer l'enregistrement
            $record->delete();
            $deletedCount++;
        }

        if ($deletedCount > 0) {
            Log::info('[CLIENT OBSERVER] AudioRecords supprimés', [
                'client_id' => $client->id,
                'deleted_count' => $deletedCount,
                'freed_bytes' => $freedBytes
            ]);
        }
    }

    /**
     * Supprime toutes les sessions d'enregistrement d'un client
     */
    private function deleteRecordingSessions(Client $client): void
    {
        $sessions = RecordingSession::withoutGlobalScopes()
            ->where('client_id', $client->id)
            ->get();

        $deletedCount = 0;

        foreach ($sessions as $session) {
            // Supprimer le dossier de chunks
            $sessionDir = storage_path("app/recordings/{$session->session_id}");
            if (is_dir($sessionDir)) {
                $this->recursiveDelete($sessionDir);
            }

            // Supprimer la session
            $session->delete();
            $deletedCount++;
        }

        if ($deletedCount > 0) {
            Log::info('[CLIENT OBSERVER] RecordingSessions supprimées', [
                'client_id' => $client->id,
                'deleted_count' => $deletedCount
            ]);
        }
    }

    /**
     * Nettoie les fichiers temporaires associés à un enregistrement audio
     */
    private function cleanupTempFiles(int $audioRecordId): void
    {
        $tempDir = storage_path('app/temp');

        $patterns = [
            "diarization_{$audioRecordId}_*.json",
            "client_audio_{$audioRecordId}_*.wav",
        ];

        foreach ($patterns as $pattern) {
            $files = glob("{$tempDir}/{$pattern}");
            foreach ($files as $file) {
                @unlink($file);
            }
        }
    }

    /**
     * Supprime récursivement un dossier
     */
    private function recursiveDelete(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $files = array_diff(scandir($path), ['.', '..']);
        foreach ($files as $file) {
            $filePath = "{$path}/{$file}";
            if (is_dir($filePath)) {
                $this->recursiveDelete($filePath);
            } else {
                unlink($filePath);
            }
        }
        rmdir($path);
    }
}
