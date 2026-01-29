<?php

namespace App\Console\Commands;

use App\Models\AudioRecord;
use App\Models\ClientComplianceDocument;
use App\Models\GeneratedDocument;
use App\Models\ImportSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Commande de migration des fichiers existants vers S3
 *
 * Migre les fichiers stockés localement vers le bucket S3 configuré.
 * Les chemins en base de données restent inchangés (ils sont relatifs).
 */
class MigrateStorageToS3 extends Command
{
    protected $signature = 'storage:migrate-to-s3
                            {--dry-run : Affiche ce qui serait migré sans migrer}
                            {--type= : Migrer un type spécifique (audio|compliance|documents|imports)}
                            {--cleanup : Supprimer les fichiers locaux après migration}';

    protected $description = 'Migre les fichiers existants du stockage local vers S3';

    private int $migratedCount = 0;
    private int $skippedCount = 0;
    private int $errorCount = 0;
    private int $migratedBytes = 0;

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $type = $this->option('type');
        $cleanup = $this->option('cleanup');

        if ($dryRun) {
            $this->warn('🔍 Mode dry-run activé - Aucun fichier ne sera migré');
        }

        $this->info('🚀 Migration des fichiers vers S3...');
        $this->newLine();

        // Vérifier la connexion S3
        if (!$dryRun) {
            try {
                Storage::put('_migration_test.txt', 'test');
                Storage::delete('_migration_test.txt');
                $this->info('✅ Connexion S3 vérifiée');
            } catch (\Exception $e) {
                $this->error('❌ Impossible de se connecter à S3 : ' . $e->getMessage());
                return Command::FAILURE;
            }
        }

        $this->newLine();

        $types = $type ? [$type] : ['audio', 'compliance', 'documents', 'imports'];

        foreach ($types as $t) {
            $this->info("📦 Migration des fichiers: {$t}");
            $method = 'migrate' . ucfirst($t);

            if (method_exists($this, $method)) {
                $this->$method($dryRun, $cleanup);
            } else {
                $this->warn("Type inconnu: {$t}");
            }

            $this->newLine();
        }

        // Résumé
        $this->newLine();
        $this->table(
            ['Métrique', 'Valeur'],
            [
                ['Fichiers migrés', $this->migratedCount],
                ['Fichiers ignorés', $this->skippedCount],
                ['Erreurs', $this->errorCount],
                ['Volume migré', $this->formatBytes($this->migratedBytes)],
            ]
        );

        if (!$dryRun && $this->migratedCount > 0) {
            Log::info('[STORAGE MIGRATION] Migration vers S3 terminée', [
                'migrated' => $this->migratedCount,
                'skipped' => $this->skippedCount,
                'errors' => $this->errorCount,
                'bytes' => $this->migratedBytes,
            ]);
        }

        return Command::SUCCESS;
    }

    /**
     * Migre les fichiers audio
     */
    private function migrateAudio(bool $dryRun, bool $cleanup): void
    {
        $records = AudioRecord::withoutGlobalScopes()
            ->whereNotNull('path')
            ->get();

        $bar = $this->output->createProgressBar($records->count());

        foreach ($records as $record) {
            $localPath = storage_path("app/public/{$record->path}");

            if (!file_exists($localPath)) {
                $this->skippedCount++;
                $bar->advance();
                continue;
            }

            try {
                $size = filesize($localPath);

                if (!$dryRun) {
                    // Upload vers S3
                    Storage::put($record->path, file_get_contents($localPath));

                    // Cleanup local si demandé
                    if ($cleanup) {
                        @unlink($localPath);
                    }
                }

                $this->migratedCount++;
                $this->migratedBytes += $size;
            } catch (\Exception $e) {
                $this->errorCount++;
                $this->error("\n  Erreur pour audio #{$record->id}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    /**
     * Migre les documents de compliance
     */
    private function migrateCompliance(bool $dryRun, bool $cleanup): void
    {
        $docs = ClientComplianceDocument::all();
        $bar = $this->output->createProgressBar($docs->count());

        foreach ($docs as $doc) {
            $localPath = storage_path("app/public/{$doc->file_path}");

            if (!file_exists($localPath)) {
                $this->skippedCount++;
                $bar->advance();
                continue;
            }

            try {
                $size = filesize($localPath);

                if (!$dryRun) {
                    Storage::put($doc->file_path, file_get_contents($localPath));

                    if ($cleanup) {
                        @unlink($localPath);
                    }
                }

                $this->migratedCount++;
                $this->migratedBytes += $size;
            } catch (\Exception $e) {
                $this->errorCount++;
                $this->error("\n  Erreur pour compliance #{$doc->id}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    /**
     * Migre les documents générés
     */
    private function migrateDocuments(bool $dryRun, bool $cleanup): void
    {
        $docs = GeneratedDocument::all();
        $bar = $this->output->createProgressBar($docs->count());

        foreach ($docs as $doc) {
            $localPath = storage_path("app/private/{$doc->file_path}");

            if (!file_exists($localPath)) {
                $this->skippedCount++;
                $bar->advance();
                continue;
            }

            try {
                $size = filesize($localPath);

                if (!$dryRun) {
                    Storage::put($doc->file_path, file_get_contents($localPath));

                    if ($cleanup) {
                        @unlink($localPath);
                    }
                }

                $this->migratedCount++;
                $this->migratedBytes += $size;
            } catch (\Exception $e) {
                $this->errorCount++;
                $this->error("\n  Erreur pour document #{$doc->id}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    /**
     * Migre les fichiers d'import
     */
    private function migrateImports(bool $dryRun, bool $cleanup): void
    {
        $sessions = ImportSession::whereNotNull('file_path')->get();
        $bar = $this->output->createProgressBar($sessions->count());

        foreach ($sessions as $session) {
            $localPath = storage_path("app/private/{$session->file_path}");

            if (!file_exists($localPath)) {
                $this->skippedCount++;
                $bar->advance();
                continue;
            }

            try {
                $size = filesize($localPath);

                if (!$dryRun) {
                    Storage::put($session->file_path, file_get_contents($localPath));

                    if ($cleanup) {
                        @unlink($localPath);
                    }
                }

                $this->migratedCount++;
                $this->migratedBytes += $size;
            } catch (\Exception $e) {
                $this->errorCount++;
                $this->error("\n  Erreur pour import #{$session->id}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
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
