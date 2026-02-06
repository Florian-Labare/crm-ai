<?php

namespace App\Console\Commands;

use App\Models\AudioRecord;
use App\Models\ClientComplianceDocument;
use App\Models\GeneratedDocument;
use App\Models\ImportSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Commande de nettoyage des fichiers orphelins
 *
 * Identifie et supprime les fichiers présents sur le système de fichiers
 * mais non référencés en base de données.
 */
class CleanupOrphanFiles extends Command
{
    protected $signature = 'storage:cleanup-orphans
                            {--dry-run : Affiche les fichiers orphelins sans les supprimer}
                            {--delete : Supprime les fichiers orphelins (confirmation requise)}
                            {--force : Supprime sans confirmation}';

    protected $description = 'Nettoie les fichiers orphelins non référencés en base de données';

    private int $orphanCount = 0;
    private int $deletedCount = 0;
    private int $deletedBytes = 0;
    private array $dbReferences = [];

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $delete = $this->option('delete');
        $force = $this->option('force');

        if (!$dryRun && !$delete) {
            $this->error('❌ Vous devez spécifier --dry-run ou --delete');
            $this->info('   Exemples:');
            $this->info('   php artisan storage:cleanup-orphans --dry-run');
            $this->info('   php artisan storage:cleanup-orphans --delete');
            return Command::FAILURE;
        }

        if ($dryRun) {
            $this->warn('🔍 Mode dry-run - Aucun fichier ne sera supprimé');
        } elseif ($delete && !$force) {
            if (!$this->confirm('⚠️  Confirmer la suppression des fichiers orphelins?')) {
                $this->info('Opération annulée');
                return Command::SUCCESS;
            }
        }

        $this->info('🔍 Recherche des fichiers orphelins...');
        $this->newLine();

        // Charger toutes les références en base de données
        $this->loadDatabaseReferences();

        // Scanner les répertoires
        $orphans = [];
        $orphans = array_merge($orphans, $this->scanDirectory('private'));
        $orphans = array_merge($orphans, $this->scanDirectory('public'));

        $this->orphanCount = count($orphans);

        if ($this->orphanCount === 0) {
            $this->info('✅ Aucun fichier orphelin trouvé');
            return Command::SUCCESS;
        }

        // Afficher les fichiers orphelins
        $this->info("📋 Fichiers orphelins détectés: {$this->orphanCount}");
        $this->newLine();

        $totalSize = array_sum(array_column($orphans, 'size'));
        $this->table(
            ['Fichier', 'Taille', 'Dernière modification'],
            array_map(function ($orphan) {
                return [
                    $orphan['relative'],
                    $this->formatBytes($orphan['size']),
                    date('Y-m-d H:i:s', $orphan['modified']),
                ];
            }, array_slice($orphans, 0, 20))
        );

        if (count($orphans) > 20) {
            $this->info("... et " . (count($orphans) - 20) . " autres fichiers");
        }

        $this->newLine();
        $this->info("Taille totale: " . $this->formatBytes($totalSize));
        $this->newLine();

        // Supprimer si demandé
        if ($delete) {
            $this->info('🗑️  Suppression en cours...');
            $bar = $this->output->createProgressBar(count($orphans));

            foreach ($orphans as $orphan) {
                try {
                    if (@unlink($orphan['path'])) {
                        $this->deletedCount++;
                        $this->deletedBytes += $orphan['size'];
                    }
                } catch (\Exception $e) {
                    $this->error("\n  Erreur pour {$orphan['relative']}: {$e->getMessage()}");
                }
                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);

            // Supprimer les répertoires vides
            $this->info('🧹 Nettoyage des répertoires vides...');
            $this->cleanupEmptyDirectories('private');
            $this->cleanupEmptyDirectories('public');

            // Résumé
            $this->newLine();
            $this->table(
                ['Métrique', 'Valeur'],
                [
                    ['Fichiers supprimés', $this->deletedCount],
                    ['Espace libéré', $this->formatBytes($this->deletedBytes)],
                ]
            );

            Log::info('[CLEANUP] Fichiers orphelins supprimés', [
                'deleted' => $this->deletedCount,
                'bytes' => $this->deletedBytes,
            ]);

            $this->info('✅ Nettoyage terminé');
        } else {
            $this->warn('💡 Pour supprimer ces fichiers, utilisez: --delete');
        }

        return Command::SUCCESS;
    }

    /**
     * Charge toutes les références de fichiers en base de données
     */
    private function loadDatabaseReferences(): void
    {
        $this->info('📚 Chargement des références en base de données...');

        // Audio records
        $audioRecords = AudioRecord::withoutGlobalScopes()
            ->whereNotNull('path')
            ->pluck('path')
            ->toArray();
        $this->dbReferences = array_merge($this->dbReferences, $audioRecords);
        $this->info("   - AudioRecord: " . count($audioRecords) . " fichiers");

        // Compliance documents
        $complianceDocs = ClientComplianceDocument::pluck('file_path')->toArray();
        $this->dbReferences = array_merge($this->dbReferences, $complianceDocs);
        $this->info("   - ClientComplianceDocument: " . count($complianceDocs) . " fichiers");

        // Generated documents
        $generatedDocs = GeneratedDocument::pluck('file_path')->toArray();
        $this->dbReferences = array_merge($this->dbReferences, $generatedDocs);
        $this->info("   - GeneratedDocument: " . count($generatedDocs) . " fichiers");

        // Import sessions
        $importSessions = ImportSession::whereNotNull('file_path')->pluck('file_path')->toArray();
        $this->dbReferences = array_merge($this->dbReferences, $importSessions);
        $this->info("   - ImportSession: " . count($importSessions) . " fichiers");

        // Normaliser les chemins (enlever 'public/', 'private/' du début)
        $this->dbReferences = array_map(function ($path) {
            return preg_replace('#^(public|private)/#', '', $path);
        }, $this->dbReferences);

        // Dédupliquer
        $this->dbReferences = array_unique($this->dbReferences);

        $this->info("   Total: " . count($this->dbReferences) . " références uniques");
        $this->newLine();
    }

    /**
     * Scanner un répertoire pour trouver les fichiers orphelins
     */
    private function scanDirectory(string $dir): array
    {
        $orphans = [];
        $path = storage_path("app/{$dir}");

        if (!is_dir($path)) {
            return $orphans;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $relativePath = str_replace(storage_path("app/{$dir}/"), '', $file->getPathname());

            // Ignorer les fichiers système et .gitignore
            if (
                str_starts_with(basename($file), '.') ||
                $relativePath === '.gitignore'
            ) {
                continue;
            }

            // Vérifier si référencé en DB (essayer avec et sans le préfixe public/private)
            $fullPath = "{$dir}/{$relativePath}";
            if (
                !in_array($relativePath, $this->dbReferences) &&
                !in_array($fullPath, $this->dbReferences)
            ) {
                $orphans[] = [
                    'path' => $file->getPathname(),
                    'relative' => $relativePath,
                    'size' => $file->getSize(),
                    'modified' => $file->getMTime(),
                ];
            }
        }

        return $orphans;
    }

    /**
     * Nettoyer les répertoires vides
     */
    private function cleanupEmptyDirectories(string $dir): void
    {
        $path = storage_path("app/{$dir}");

        if (!is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir() && $this->isEmptyDirectory($file->getPathname())) {
                @rmdir($file->getPathname());
            }
        }
    }

    /**
     * Vérifie si un répertoire est vide
     */
    private function isEmptyDirectory(string $dir): bool
    {
        $handle = opendir($dir);
        while (false !== ($entry = readdir($handle))) {
            if ($entry !== '.' && $entry !== '..') {
                closedir($handle);
                return false;
            }
        }
        closedir($handle);
        return true;
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
