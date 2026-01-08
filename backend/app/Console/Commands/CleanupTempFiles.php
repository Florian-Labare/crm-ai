<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Commande de nettoyage des fichiers temporaires orphelins
 *
 * Cette commande supprime :
 * - Les fichiers de diarisation temporaires (JSON, WAV)
 * - Les chunks d'enregistrement non finalisés après 24h
 * - Les fichiers audio temporaires
 */
class CleanupTempFiles extends Command
{
    protected $signature = 'audio:cleanup-temp
                            {--dry-run : Affiche ce qui serait supprimé sans supprimer}
                            {--hours=24 : Âge minimum des fichiers à supprimer (en heures)}';

    protected $description = 'Nettoie les fichiers temporaires orphelins du système audio';

    private int $deletedCount = 0;
    private int $freedBytes = 0;

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $minAgeHours = (int) $this->option('hours');
        $minAgeTimestamp = now()->subHours($minAgeHours)->timestamp;

        $this->info($dryRun ? '🔍 Mode dry-run activé' : '🧹 Nettoyage des fichiers temporaires...');
        $this->newLine();

        // 1. Nettoyer le dossier temp
        $this->cleanupTempDirectory($minAgeTimestamp, $dryRun);

        // 2. Nettoyer les chunks orphelins
        $this->cleanupOrphanedChunks($minAgeTimestamp, $dryRun);

        // 3. Nettoyer les sessions d'enregistrement abandonnées
        $this->cleanupAbandonedSessions($minAgeTimestamp, $dryRun);

        // Résumé
        $this->newLine();
        $this->info(sprintf(
            '✅ %s: %d fichiers (%s)',
            $dryRun ? 'Fichiers à supprimer' : 'Fichiers supprimés',
            $this->deletedCount,
            $this->formatBytes($this->freedBytes)
        ));

        if (!$dryRun && $this->deletedCount > 0) {
            Log::info('[CLEANUP] Nettoyage des fichiers temporaires effectué', [
                'deleted_count' => $this->deletedCount,
                'freed_bytes' => $this->freedBytes,
                'min_age_hours' => $minAgeHours
            ]);
        }

        return Command::SUCCESS;
    }

    /**
     * Nettoie le dossier storage/app/temp
     */
    private function cleanupTempDirectory(int $minAgeTimestamp, bool $dryRun): void
    {
        $tempDir = storage_path('app/temp');

        if (!is_dir($tempDir)) {
            $this->line('📁 Dossier temp inexistant, rien à nettoyer');
            return;
        }

        $this->info('📁 Nettoyage du dossier temp...');

        $patterns = [
            'diarization_*.json',   // Résultats de diarisation
            'client_audio_*.wav',   // Audio client extrait
            '*.tmp',                // Fichiers temporaires génériques
        ];

        foreach ($patterns as $pattern) {
            $files = glob("{$tempDir}/{$pattern}");
            foreach ($files as $file) {
                if (filemtime($file) < $minAgeTimestamp) {
                    $this->deleteFile($file, $dryRun);
                }
            }
        }
    }

    /**
     * Nettoie les chunks d'enregistrement orphelins
     */
    private function cleanupOrphanedChunks(int $minAgeTimestamp, bool $dryRun): void
    {
        $recordingsDir = storage_path('app/recordings');

        if (!is_dir($recordingsDir)) {
            return;
        }

        $this->info('📁 Nettoyage des chunks orphelins...');

        // Parcourir les dossiers de session
        $sessionDirs = glob("{$recordingsDir}/*", GLOB_ONLYDIR);

        foreach ($sessionDirs as $sessionDir) {
            $sessionId = basename($sessionDir);

            // Vérifier si la session existe toujours en base
            $sessionExists = \App\Models\RecordingSession::where('session_id', $sessionId)
                ->where('status', '!=', 'finalized')
                ->exists();

            // Si la session n'existe pas ou est finalisée, vérifier l'âge du dossier
            if (!$sessionExists) {
                $dirAge = filemtime($sessionDir);
                if ($dirAge < $minAgeTimestamp) {
                    $this->deleteDirectory($sessionDir, $dryRun);
                }
            }
        }
    }

    /**
     * Nettoie les sessions d'enregistrement abandonnées (> 24h sans finalisation)
     */
    private function cleanupAbandonedSessions(int $minAgeTimestamp, bool $dryRun): void
    {
        $this->info('📁 Nettoyage des sessions abandonnées...');

        $abandonedSessions = \App\Models\RecordingSession::where('status', 'recording')
            ->where('updated_at', '<', now()->subHours((int) $this->option('hours')))
            ->get();

        foreach ($abandonedSessions as $session) {
            $this->line("  - Session {$session->session_id} (créée le {$session->created_at})");

            if (!$dryRun) {
                // Supprimer les fichiers de chunks
                $sessionDir = storage_path("app/recordings/{$session->session_id}");
                if (is_dir($sessionDir)) {
                    $this->deleteDirectory($sessionDir, false);
                }

                // Marquer la session comme failed
                $session->update(['status' => 'failed']);

                Log::info('[CLEANUP] Session abandonnée nettoyée', [
                    'session_id' => $session->session_id,
                    'created_at' => $session->created_at
                ]);
            }

            $this->deletedCount++;
        }
    }

    /**
     * Supprime un fichier
     */
    private function deleteFile(string $path, bool $dryRun): void
    {
        $size = filesize($path);
        $filename = basename($path);

        $this->line("  - {$filename} (" . $this->formatBytes($size) . ")");

        if (!$dryRun) {
            @unlink($path);
        }

        $this->deletedCount++;
        $this->freedBytes += $size;
    }

    /**
     * Supprime un dossier et son contenu
     */
    private function deleteDirectory(string $path, bool $dryRun): void
    {
        $dirname = basename($path);
        $totalSize = $this->getDirectorySize($path);

        $this->line("  - Dossier {$dirname}/ (" . $this->formatBytes($totalSize) . ")");

        if (!$dryRun) {
            $this->recursiveDelete($path);
        }

        $this->deletedCount++;
        $this->freedBytes += $totalSize;
    }

    /**
     * Calcule la taille d'un dossier
     */
    private function getDirectorySize(string $path): int
    {
        $size = 0;
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            $size += $file->getSize();
        }

        return $size;
    }

    /**
     * Supprime récursivement un dossier
     */
    private function recursiveDelete(string $path): void
    {
        if (is_dir($path)) {
            $files = array_diff(scandir($path), ['.', '..']);
            foreach ($files as $file) {
                $this->recursiveDelete("{$path}/{$file}");
            }
            rmdir($path);
        } else {
            unlink($path);
        }
    }

    /**
     * Formate une taille en bytes
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
