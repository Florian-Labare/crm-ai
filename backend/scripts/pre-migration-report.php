#!/usr/bin/env php
<?php

/**
 * Script de rapport pré-migration S3
 *
 * Génère un rapport détaillé avant la migration:
 * - Inventaire des fichiers locaux
 * - Fichiers référencés en DB vs orphelins
 * - Espace à migrer vs à conserver
 * - Vérification connexion S3
 */

require __DIR__ . '/../vendor/autoload.php';

use Illuminate\Support\Facades\Storage;
use App\Models\AudioRecord;
use App\Models\ClientComplianceDocument;
use App\Models\GeneratedDocument;
use App\Models\ImportSession;

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "📊 Rapport de Pré-Migration S3\n";
echo str_repeat('=', 70) . "\n\n";

$report = [
    'timestamp' => date('Y-m-d H:i:s'),
    's3_connection' => false,
    'local_storage' => [],
    'database_references' => [],
    'orphan_files' => [],
    'migration_estimate' => [],
];

// 1. Test connexion S3
echo "1️⃣  Test de connexion S3/MinIO...\n";
try {
    $testFile = '_pre_migration_test_' . time() . '.txt';
    Storage::disk('s3')->put($testFile, 'test');
    Storage::disk('s3')->delete($testFile);
    $report['s3_connection'] = true;
    echo "   ✅ Connexion S3 fonctionnelle\n\n";
} catch (\Exception $e) {
    echo "   ❌ Erreur: " . $e->getMessage() . "\n";
    echo "   ⚠️  Migration impossible sans connexion S3\n\n";
    $report['s3_connection'] = false;
}

// 2. Scanner le stockage local
echo "2️⃣  Scan du stockage local...\n";
$storagePath = storage_path('app');
$directories = [
    'templates' => 'templates',
    'private' => 'private',
    'public' => 'public',
    'recordings' => 'recordings',
    'temp' => 'temp',
];

foreach ($directories as $key => $dir) {
    $path = $storagePath . '/' . $dir;
    if (!is_dir($path)) {
        echo sprintf("   %-15s : (inexistant)\n", $key);
        continue;
    }

    $files = scanDirectoryRecursive($path);
    $totalSize = array_sum(array_column($files, 'size'));
    $fileCount = count($files);

    $report['local_storage'][$key] = [
        'path' => $path,
        'files' => $fileCount,
        'size' => $totalSize,
        'files_list' => $files,
    ];

    echo sprintf(
        "   %-15s : %d fichiers, %s\n",
        $key,
        $fileCount,
        formatBytes($totalSize)
    );
}
echo "\n";

// 3. Références en base de données
echo "3️⃣  Références en base de données...\n";

// Audio records
$audioRecords = AudioRecord::withoutGlobalScopes()
    ->whereNotNull('path')
    ->get(['id', 'path']);
echo sprintf("   %-30s : %d enregistrements\n", 'AudioRecord', $audioRecords->count());
$report['database_references']['audio'] = $audioRecords->pluck('path')->toArray();

// Compliance documents
$complianceDocs = ClientComplianceDocument::all(['id', 'file_path']);
echo sprintf("   %-30s : %d enregistrements\n", 'ClientComplianceDocument', $complianceDocs->count());
$report['database_references']['compliance'] = $complianceDocs->pluck('file_path')->toArray();

// Generated documents
$generatedDocs = GeneratedDocument::all(['id', 'file_path']);
echo sprintf("   %-30s : %d enregistrements\n", 'GeneratedDocument', $generatedDocs->count());
$report['database_references']['documents'] = $generatedDocs->pluck('file_path')->toArray();

// Import sessions
$importSessions = ImportSession::whereNotNull('file_path')->get(['id', 'file_path']);
echo sprintf("   %-30s : %d enregistrements\n", 'ImportSession', $importSessions->count());
$report['database_references']['imports'] = $importSessions->pluck('file_path')->toArray();

$totalDbRefs = $audioRecords->count() + $complianceDocs->count() + $generatedDocs->count() + $importSessions->count();
echo sprintf("\n   Total: %d fichiers référencés\n\n", $totalDbRefs);

// 4. Détecter les fichiers orphelins
echo "4️⃣  Détection des fichiers orphelins...\n";
$dbPaths = array_merge(
    $report['database_references']['audio'],
    $report['database_references']['compliance'],
    $report['database_references']['documents'],
    $report['database_references']['imports']
);

// Normaliser les chemins DB (enlever 'public/', 'private/' du début)
$dbPathsNormalized = array_map(function($path) {
    return preg_replace('#^(public|private)/#', '', $path);
}, $dbPaths);

$orphans = [];
foreach (['private', 'public'] as $dir) {
    if (!isset($report['local_storage'][$dir])) continue;

    foreach ($report['local_storage'][$dir]['files_list'] as $file) {
        $relativePath = str_replace(storage_path("app/{$dir}/"), '', $file['path']);

        // Ignorer les fichiers temporaires et système
        if (
            str_starts_with($relativePath, '.') ||
            str_contains($relativePath, '/.') ||
            $relativePath === '.gitignore'
        ) {
            continue;
        }

        // Vérifier si référencé en DB
        $fullPath = "{$dir}/{$relativePath}";
        if (!in_array($relativePath, $dbPathsNormalized) && !in_array($fullPath, $dbPaths)) {
            $orphans[] = [
                'path' => $file['path'],
                'relative' => $relativePath,
                'size' => $file['size'],
                'modified' => $file['modified'],
            ];
        }
    }
}

$report['orphan_files'] = $orphans;
$orphanSize = array_sum(array_column($orphans, 'size'));

echo sprintf("   Fichiers orphelins détectés : %d (%s)\n", count($orphans), formatBytes($orphanSize));
if (count($orphans) > 0 && count($orphans) <= 10) {
    foreach ($orphans as $orphan) {
        echo sprintf("   - %s (%s)\n", $orphan['relative'], formatBytes($orphan['size']));
    }
} elseif (count($orphans) > 10) {
    foreach (array_slice($orphans, 0, 5) as $orphan) {
        echo sprintf("   - %s (%s)\n", $orphan['relative'], formatBytes($orphan['size']));
    }
    echo sprintf("   ... et %d autres fichiers\n", count($orphans) - 5);
}
echo "\n";

// 5. Estimation de migration
echo "5️⃣  Estimation de migration...\n";
$privateSize = $report['local_storage']['private']['size'] ?? 0;
$publicSize = $report['local_storage']['public']['size'] ?? 0;
$templatesSize = $report['local_storage']['templates']['size'] ?? 0;
$recordingsSize = $report['local_storage']['recordings']['size'] ?? 0;
$tempSize = $report['local_storage']['temp']['size'] ?? 0;

$toMigrate = $privateSize + $publicSize;
$toKeepLocal = $templatesSize + $recordingsSize + $tempSize;

$report['migration_estimate'] = [
    'to_migrate' => $toMigrate,
    'to_keep_local' => $toKeepLocal,
    'orphans' => $orphanSize,
    'space_freed' => $toMigrate - $orphanSize,
];

echo sprintf("   À migrer vers S3       : %s (private + public)\n", formatBytes($toMigrate));
echo sprintf("   À conserver localement : %s (templates + temp + recordings)\n", formatBytes($toKeepLocal));
echo sprintf("   Fichiers orphelins     : %s\n", formatBytes($orphanSize));
echo sprintf("   Espace libéré (net)    : %s\n", formatBytes($toMigrate - $orphanSize));
echo "\n";

// 6. Résumé et recommandations
echo "6️⃣  Résumé et recommandations:\n";
echo str_repeat('-', 70) . "\n";

if (!$report['s3_connection']) {
    echo "   ❌ MIGRATION IMPOSSIBLE\n";
    echo "      - Connexion S3 échouée\n";
    echo "      - Vérifier la configuration (voir check-s3-config.php)\n\n";
} else {
    echo "   ✅ Prêt pour la migration\n\n";

    echo "   📝 Étapes recommandées:\n";
    echo "      1. Backup de la base de données\n";
    echo "      2. Migration test : php artisan storage:migrate-to-s3 --dry-run\n";
    echo "      3. Migration réelle : php artisan storage:migrate-to-s3\n";

    if (count($orphans) > 0) {
        echo "      4. Nettoyer les orphelins : php artisan storage:cleanup-orphans\n";
    }

    echo "      5. Vérifier l'application fonctionne correctement\n";
    echo "      6. Cleanup local : php artisan storage:migrate-to-s3 --cleanup\n\n";
}

echo "   💾 Rapport sauvegardé : storage/logs/pre-migration-report.json\n";

// Sauvegarder le rapport
$reportPath = storage_path('logs/pre-migration-report.json');
@mkdir(dirname($reportPath), 0755, true);
file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT));

echo "\n✅ Rapport terminé!\n";

// Helper functions
function scanDirectoryRecursive(string $dir): array
{
    $files = [];

    if (!is_dir($dir)) {
        return $files;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $files[] = [
                'path' => $file->getPathname(),
                'size' => $file->getSize(),
                'modified' => $file->getMTime(),
            ];
        }
    }

    return $files;
}

function formatBytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}
