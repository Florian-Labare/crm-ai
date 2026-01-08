<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Service de vérification de santé de Pyannote
 *
 * Permet de vérifier la disponibilité du système de diarisation
 * et de mettre en cache le résultat pour éviter des vérifications répétées
 */
class PyannoteHealthService
{
    private const CACHE_KEY = 'pyannote_health_status';
    private const CACHE_TTL = 3600; // 1 heure

    /**
     * Vérifie si pyannote est disponible et fonctionnel
     *
     * @param bool $forceRefresh Forcer une nouvelle vérification
     * @return array{available: bool, checks: array, errors: array, warnings: array}
     */
    public function check(bool $forceRefresh = false): array
    {
        // Retourner le cache si disponible et non forcé
        if (!$forceRefresh && Cache::has(self::CACHE_KEY)) {
            return Cache::get(self::CACHE_KEY);
        }

        Log::info('[PYANNOTE HEALTH] Vérification de la disponibilité de pyannote...');

        $result = $this->runHealthCheck();

        // Mettre en cache le résultat
        Cache::put(self::CACHE_KEY, $result, self::CACHE_TTL);

        // Logger le résultat
        if ($result['available']) {
            Log::info('[PYANNOTE HEALTH] ✅ Pyannote disponible et fonctionnel', [
                'checks' => array_map(fn($c) => $c['status'], $result['checks'])
            ]);
        } else {
            Log::warning('[PYANNOTE HEALTH] ⚠️ Pyannote non disponible', [
                'errors' => $result['errors'],
                'warnings' => $result['warnings']
            ]);
        }

        return $result;
    }

    /**
     * Vérifie rapidement si pyannote est disponible (depuis le cache ou check rapide)
     */
    public function isAvailable(): bool
    {
        $status = $this->check();
        return $status['available'];
    }

    /**
     * Efface le cache et force une nouvelle vérification
     */
    public function refresh(): array
    {
        Cache::forget(self::CACHE_KEY);
        return $this->check(true);
    }

    /**
     * Exécute le script Python de health check
     */
    private function runHealthCheck(): array
    {
        $scriptPath = base_path('scripts/check_pyannote.py');

        if (!file_exists($scriptPath)) {
            return [
                'available' => false,
                'checks' => [],
                'errors' => ['Health check script not found'],
                'warnings' => [],
                'checked_at' => now()->toISOString()
            ];
        }

        // SECURITE: Ne pas passer le token dans la ligne de commande (visible dans ps aux)
        $command = sprintf(
            'python3 %s',
            escapeshellarg($scriptPath)
        );

        // Préparer l'environnement sécurisé (token non visible dans ps aux)
        $hfToken = config('services.huggingface.token') ?: env('HUGGINGFACE_TOKEN');
        $processEnv = array_merge($_ENV, $_SERVER, [
            'HUGGINGFACE_TOKEN' => $hfToken ?? '',
            'HOME' => $_SERVER['HOME'] ?? '/tmp',
            'PATH' => $_SERVER['PATH'] ?? '/usr/local/bin:/usr/bin:/bin',
        ]);
        // Nettoyer les variables qui ne sont pas des strings
        $processEnv = array_filter($processEnv, fn($v) => is_string($v));

        // Exécuter avec timeout de 30 secondes
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ];

        // SECURITE: Passer l'environnement via le 5ème paramètre de proc_open
        $process = proc_open($command, $descriptors, $pipes, null, $processEnv);

        if (!is_resource($process)) {
            return [
                'available' => false,
                'checks' => [],
                'errors' => ['Failed to start health check process'],
                'warnings' => [],
                'checked_at' => now()->toISOString()
            ];
        }

        fclose($pipes[0]);

        // Timeout de 30 secondes
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $output = '';
        $startTime = time();
        $timeout = 30;

        while (true) {
            $status = proc_get_status($process);

            if (!$status['running']) {
                break;
            }

            if ((time() - $startTime) > $timeout) {
                proc_terminate($process, 9);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);

                return [
                    'available' => false,
                    'checks' => [],
                    'errors' => ['Health check timeout (30s)'],
                    'warnings' => [],
                    'checked_at' => now()->toISOString()
                ];
            }

            usleep(100000);
        }

        $output = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        // Parser le JSON de sortie
        $result = json_decode($output, true);

        if (!$result) {
            return [
                'available' => false,
                'checks' => [],
                'errors' => ['Invalid health check response: ' . substr($output . $stderr, 0, 200)],
                'warnings' => [],
                'checked_at' => now()->toISOString()
            ];
        }

        $result['checked_at'] = now()->toISOString();

        return $result;
    }
}
