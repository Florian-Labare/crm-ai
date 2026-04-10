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
class MigrateStorageToS3 extends Command {
    protected $signature = 'storage:migrate-to-s3
                            {--dry-run : Affiche ce qui serait migré sans migrer}
                            {--type= : Migrer un type spécifique (audio|compliance|documents|imports)}
                            {--cleanup : Supprimer les fichiers locaux après migration}
                            {--scan-local : Scanner les fichiers physiques en plus des références DB}';

    protected $description = 'Migre les fichiers existants du stockage local vers S3';

    private int $migratedCount = 0;

    private int $skippedCount = 0;

    private int $errorCount = 0;

    private int $migratedBytes = 0;

    public function handle(): int {
        $dryRun = $this->option('dry-run');
        $type = $this->option('type');
        $cleanup = $this->option('cleanup');
        $scanLocal = $this->option('scan-local');

        if ($dryRun) {
            $this->warn('🔍 Mode dry-run activé - Aucun fichier ne sera migré');
        }

        if ($cleanup && $dryRun) {
            $this->error('❌ Les options --cleanup et --dry-run sont incompatibles');

            return Command::FAILURE;
        }

        $this->info('🚀 Migration des fichiers vers S3...');
        $this->newLine();

        // Vérifier la configuration S3
        $this->info('📋 Configuration S3:');
        $this->info('   Bucket: '.config('filesystems.disks.s3.bucket'));
        $this->info('   Region: '.config('filesystems.disks.s3.region'));
        $this->info('   Endpoint: '.(config('filesystems.disks.s3.endpoint') ?: 'AWS par défaut'));
        $this->newLine();

        // Vérifier la connexion S3
        if (! $dryRun) {
            try {
                Storage::disk('s3')->put('_migration_test.txt', 'test');
                Storage::disk('s3')->delete('_migration_test.txt');
                $this->info('✅ Connexion S3 vérifiée');
            } catch (\Exception $e) {
                $this->error('❌ Impossible de se connecter à S3 : '.$e->getMessage());
                $this->error('   Vérifiez votre configuration avec: php scripts/check-s3-config.php');

                return Command::FAILURE;
            }
        }

        $this->newLine();

        $types = $type ? [$type] : ['audio', 'compliance', 'documents', 'imports'];

        foreach ($types as $t) {
            $this->info("📦 Migration des fichiers: {$t}");
            $method = 'migrate'.ucfirst($t);

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

        if (! $dryRun && $this->migratedCount > 0) {
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
    private function migrateAudio(bool $dryRun, bool $cleanup): void {
        $records = AudioRecord::withoutGlobalScopes()
            ->whereNotNull('path')
            ->get();

        if ($records->count() === 0) {
            $this->info('   Aucun fichier audio à migrer');

            return;
        }

        $bar = $this->output->createProgressBar($records->count());

        foreach ($records as $record) {
            // Essayer plusieurs chemins possibles
            $possiblePaths = [
                storage_path("app/public/{$record->path}"),
                storage_path("app/{$record->path}"),
                storage_path("app/private/{$record->path}"),
            ];

            $localPath = null;
            foreach ($possiblePaths as $path) {
                if (file_exists($path)) {
                    $localPath = $path;
                    break;
                }
            }

            if (! $localPath) {
                $this->skippedCount++;
                $bar->advance();

                continue;
            }

            try {
                $size = filesize($localPath);

                if (! $dryRun) {
                    // Upload vers S3 avec le chemin DB
                    Storage::disk('s3')->put($record->path, file_get_contents($localPath));

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
    private function migrateCompliance(bool $dryRun, bool $cleanup): void {
        $docs = ClientComplianceDocument::all();

        if ($docs->count() === 0) {
            $this->info('   Aucun document de compliance à migrer');

            return;
        }

        $bar = $this->output->createProgressBar($docs->count());

        foreach ($docs as $doc) {
            // Essayer plusieurs chemins possibles
            $possiblePaths = [
                storage_path("app/public/{$doc->file_path}"),
                storage_path("app/{$doc->file_path}"),
                storage_path("app/private/{$doc->file_path}"),
            ];

            $localPath = null;
            foreach ($possiblePaths as $path) {
                if (file_exists($path)) {
                    $localPath = $path;
                    break;
                }
            }

            if (! $localPath) {
                $this->skippedCount++;
                $bar->advance();

                continue;
            }

            try {
                $size = filesize($localPath);

                if (! $dryRun) {
                    Storage::disk('s3')->put($doc->file_path, file_get_contents($localPath));

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
    private function migrateDocuments(bool $dryRun, bool $cleanup): void {
        $docs = GeneratedDocument::all();

        if ($docs->count() === 0) {
            $this->info('   Aucun document généré à migrer');

            return;
        }

        $bar = $this->output->createProgressBar($docs->count());

        foreach ($docs as $doc) {
            // Essayer plusieurs chemins possibles
            $possiblePaths = [
                storage_path("app/private/{$doc->file_path}"),
                storage_path("app/{$doc->file_path}"),
                storage_path("app/public/{$doc->file_path}"),
            ];

            $localPath = null;
            foreach ($possiblePaths as $path) {
                if (file_exists($path)) {
                    $localPath = $path;
                    break;
                }
            }

            if (! $localPath) {
                $this->skippedCount++;
                $bar->advance();

                continue;
            }

            try {
                $size = filesize($localPath);

                if (! $dryRun) {
                    Storage::disk('s3')->put($doc->file_path, file_get_contents($localPath));

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
    private function migrateImports(bool $dryRun, bool $cleanup): void {
        $sessions = ImportSession::whereNotNull('file_path')->get();

        if ($sessions->count() === 0) {
            $this->info('   Aucun fichier d\'import à migrer');

            return;
        }

        $bar = $this->output->createProgressBar($sessions->count());

        foreach ($sessions as $session) {
            // Essayer plusieurs chemins possibles
            $possiblePaths = [
                storage_path("app/private/{$session->file_path}"),
                storage_path("app/{$session->file_path}"),
                storage_path("app/public/{$session->file_path}"),
            ];

            $localPath = null;
            foreach ($possiblePaths as $path) {
                if (file_exists($path)) {
                    $localPath = $path;
                    break;
                }
            }

            if (! $localPath) {
                $this->skippedCount++;
                $bar->advance();

                continue;
            }

            try {
                $size = filesize($localPath);

                if (! $dryRun) {
                    Storage::disk('s3')->put($session->file_path, file_get_contents($localPath));

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
