#!/usr/bin/env php
<?php

/**
 * Script de vérification de la configuration S3
 *
 * Usage: php backend/scripts/check-s3-config.php
 *
 * Vérifie:
 * - Variables d'environnement
 * - Connexion au bucket S3
 * - Permissions d'écriture/lecture/suppression
 * - Configuration des disks Laravel
 */

require __DIR__ . '/../vendor/autoload.php';

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;

// Bootstrap Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

class S3ConfigChecker
{
    private array $errors = [];
    private array $warnings = [];
    private array $success = [];

    public function run(): int
    {
        $this->printHeader();

        $this->checkEnvironmentVariables();
        $this->checkFilesystemConfig();
        $this->checkS3Connection();
        $this->checkS3Permissions();
        $this->checkLocalDisks();

        $this->printSummary();

        return empty($this->errors) ? 0 : 1;
    }

    private function printHeader(): void
    {
        echo "\n";
        echo "========================================\n";
        echo "  Vérification Configuration S3\n";
        echo "========================================\n\n";
    }

    private function checkEnvironmentVariables(): void
    {
        echo "[1/5] Vérification variables d'environnement...\n";

        $required = [
            'FILESYSTEM_DISK',
            'AWS_ACCESS_KEY_ID',
            'AWS_SECRET_ACCESS_KEY',
            'AWS_DEFAULT_REGION',
            'AWS_BUCKET',
        ];

        $optional = [
            'AWS_URL',
            'AWS_ENDPOINT',
            'AWS_USE_PATH_STYLE_ENDPOINT',
        ];

        foreach ($required as $var) {
            $value = env($var);
            if (empty($value)) {
                $this->errors[] = "Variable manquante: {$var}";
            } else {
                $display = in_array($var, ['AWS_SECRET_ACCESS_KEY'])
                    ? str_repeat('*', 20)
                    : $value;
                $this->success[] = "{$var} = {$display}";
            }
        }

        foreach ($optional as $var) {
            $value = env($var);
            if (empty($value)) {
                $this->warnings[] = "Variable optionnelle non définie: {$var}";
            } else {
                $this->success[] = "{$var} = {$value}";
            }
        }

        // Vérifier que le disk par défaut est S3
        if (env('FILESYSTEM_DISK') !== 's3') {
            $this->warnings[] = "FILESYSTEM_DISK n'est pas configuré sur 's3' (valeur actuelle: " . env('FILESYSTEM_DISK') . ")";
        }

        echo "\n";
    }

    private function checkFilesystemConfig(): void
    {
        echo "[2/5] Vérification configuration Laravel...\n";

        // Vérifier que les disks sont bien configurés
        $disks = ['s3', 'local', 'templates', 'temp', 'recordings'];

        foreach ($disks as $disk) {
            try {
                $config = Config::get("filesystems.disks.{$disk}");
                if ($config) {
                    $driver = $config['driver'] ?? 'N/A';
                    $this->success[] = "Disk '{$disk}' configuré (driver: {$driver})";
                } else {
                    $this->errors[] = "Disk '{$disk}' non configuré";
                }
            } catch (\Exception $e) {
                $this->errors[] = "Erreur lors de la vérification du disk '{$disk}': " . $e->getMessage();
            }
        }

        echo "\n";
    }

    private function checkS3Connection(): void
    {
        echo "[3/5] Test de connexion S3...\n";

        try {
            $disk = Storage::disk('s3');

            // Tenter de lister les fichiers (ne devrait pas échouer même si vide)
            $files = $disk->files('/', false);
            $this->success[] = "Connexion S3 réussie (bucket accessible)";
            $this->success[] = "Fichiers trouvés à la racine: " . count($files);

        } catch (\Exception $e) {
            $this->errors[] = "Impossible de se connecter à S3: " . $e->getMessage();
        }

        echo "\n";
    }

    private function checkS3Permissions(): void
    {
        echo "[4/5] Test des permissions S3...\n";

        try {
            $disk = Storage::disk('s3');
            $testFile = 'test-permissions-' . time() . '.txt';
            $testContent = 'Test de permissions S3';

            // Test d'écriture (PutObject)
            $disk->put($testFile, $testContent);
            $this->success[] = "Permission PutObject: OK";

            // Test de lecture (GetObject)
            $content = $disk->get($testFile);
            if ($content === $testContent) {
                $this->success[] = "Permission GetObject: OK";
            } else {
                $this->errors[] = "Permission GetObject: échec de lecture";
            }

            // Test d'existence (HeadObject)
            if ($disk->exists($testFile)) {
                $this->success[] = "Permission HeadObject: OK";
            } else {
                $this->errors[] = "Permission HeadObject: échec";
            }

            // Test de suppression (DeleteObject)
            $disk->delete($testFile);
            if (!$disk->exists($testFile)) {
                $this->success[] = "Permission DeleteObject: OK";
            } else {
                $this->errors[] = "Permission DeleteObject: échec de suppression";
            }

        } catch (\Exception $e) {
            $this->errors[] = "Erreur lors du test de permissions: " . $e->getMessage();
        }

        echo "\n";
    }

    private function checkLocalDisks(): void
    {
        echo "[5/5] Vérification des disks locaux...\n";

        $localPaths = [
            'templates' => storage_path('app/templates'),
            'temp' => storage_path('app/temp'),
            'recordings' => storage_path('app/recordings'),
        ];

        foreach ($localPaths as $name => $path) {
            if (is_dir($path)) {
                if (is_writable($path)) {
                    $this->success[] = "Répertoire '{$name}' existe et est accessible en écriture";
                } else {
                    $this->warnings[] = "Répertoire '{$name}' existe mais n'est pas accessible en écriture";
                }
            } else {
                $this->warnings[] = "Répertoire '{$name}' n'existe pas (sera créé automatiquement si nécessaire)";
            }
        }

        echo "\n";
    }

    private function printSummary(): void
    {
        echo "========================================\n";
        echo "  Résumé\n";
        echo "========================================\n\n";

        if (!empty($this->success)) {
            echo "✓ Succès (" . count($this->success) . "):\n";
            foreach ($this->success as $msg) {
                echo "  ✓ {$msg}\n";
            }
            echo "\n";
        }

        if (!empty($this->warnings)) {
            echo "⚠ Avertissements (" . count($this->warnings) . "):\n";
            foreach ($this->warnings as $msg) {
                echo "  ⚠ {$msg}\n";
            }
            echo "\n";
        }

        if (!empty($this->errors)) {
            echo "✗ Erreurs (" . count($this->errors) . "):\n";
            foreach ($this->errors as $msg) {
                echo "  ✗ {$msg}\n";
            }
            echo "\n";
        }

        if (empty($this->errors)) {
            echo "========================================\n";
            echo "  Configuration S3 valide!\n";
            echo "========================================\n\n";
        } else {
            echo "========================================\n";
            echo "  Configuration S3 invalide\n";
            echo "  Veuillez corriger les erreurs ci-dessus\n";
            echo "========================================\n\n";
        }
    }
}

// Exécuter le script
$checker = new S3ConfigChecker();
exit($checker->run());
