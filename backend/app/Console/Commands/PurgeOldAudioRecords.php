<?php

namespace App\Console\Commands;

use App\Models\AudioRecord;
use App\Models\DiarizationLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Commande de purge des enregistrements audio anciens
 *
 * RGPD : Supprime les fichiers audio après une période de rétention configurable
 * Par défaut : 30 jours après le traitement
 *
 * Note : Les transcriptions textuelles sont conservées (données métier)
 * Seuls les fichiers audio bruts sont supprimés
 */
class PurgeOldAudioRecords extends Command {
    protected $signature = 'audio:purge-old
                            {--days=30 : Nombre de jours de rétention}
                            {--dry-run : Affiche ce qui serait supprimé sans supprimer}
                            {--include-transcriptions : Supprime aussi les transcriptions (RGPD complet)}
                            {--team= : Limiter à une team spécifique}';

    protected $description = 'Supprime les fichiers audio de plus de X jours (conformité RGPD)';

    private int $deletedFiles = 0;

    private int $deletedRecords = 0;

    private int $freedBytes = 0;

    public function handle(): int {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        $includeTranscriptions = $this->option('include-transcriptions');
        $teamId = $this->option('team');

        $cutoffDate = now()->subDays($days);

        $this->info($dryRun ? '🔍 Mode dry-run activé' : '🗑️ Purge des anciens enregistrements audio...');
        $this->info("📅 Suppression des fichiers antérieurs au {$cutoffDate->format('Y-m-d H:i')}");
        $this->newLine();

        // Construire la requête
        $query = AudioRecord::withoutGlobalScopes()
            ->where('created_at', '<', $cutoffDate)
            ->where('status', 'done'); // Ne supprimer que les enregistrements traités

        if ($teamId) {
            $query->where('team_id', $teamId);
            $this->info("🏢 Limité à la team #{$teamId}");
        }

        $records = $query->get();

        if ($records->isEmpty()) {
            $this->info('✅ Aucun enregistrement à purger.');

            return Command::SUCCESS;
        }

        $this->info("📊 {$records->count()} enregistrements trouvés");
        $this->newLine();

        $progressBar = $this->output->createProgressBar($records->count());
        $progressBar->start();

        foreach ($records as $record) {
            $this->processRecord($record, $dryRun, $includeTranscriptions);
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Résumé
        $this->table(
            ['Métrique', 'Valeur'],
            [
                ['Fichiers audio supprimés', $this->deletedFiles],
                ['Espace libéré', $this->formatBytes($this->freedBytes)],
                ['Enregistrements supprimés', $includeTranscriptions ? $this->deletedRecords : 'N/A (transcriptions conservées)'],
            ]
        );

        if (! $dryRun && $this->deletedFiles > 0) {
            Log::info('[RGPD PURGE] Purge des anciens enregistrements effectuée', [
                'retention_days' => $days,
                'deleted_files' => $this->deletedFiles,
                'deleted_records' => $this->deletedRecords,
                'freed_bytes' => $this->freedBytes,
                'team_id' => $teamId,
                'include_transcriptions' => $includeTranscriptions,
            ]);
        }

        return Command::SUCCESS;
    }

    /**
     * Traite un enregistrement pour suppression
     */
    private function processRecord(AudioRecord $record, bool $dryRun, bool $includeTranscriptions): void {
        // 1. Supprimer le fichier audio (depuis S3)
        if ($record->path && Storage::exists($record->path)) {
            $size = Storage::size($record->path);

            if (! $dryRun) {
                Storage::delete($record->path);

                // Mettre à jour le record pour indiquer que le fichier a été supprimé
                $record->update([
                    'path' => null,
                ]);
            }

            $this->deletedFiles++;
            $this->freedBytes += $size;
        }

        // 2. Supprimer les logs de diarisation si demandé
        if ($includeTranscriptions && ! $dryRun) {
            DiarizationLog::where('audio_record_id', $record->id)->delete();
        }

        // 3. Supprimer complètement l'enregistrement si demandé
        if ($includeTranscriptions) {
            if (! $dryRun) {
                $record->delete();
            }
            $this->deletedRecords++;
        }
    }

    /**
     * Formate une taille en bytes
     */
    private function formatBytes(int $bytes): string {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2).' '.$units[$i];
    }
}
