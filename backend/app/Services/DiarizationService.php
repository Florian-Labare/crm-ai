<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Service de diarisation audio avec pyannote
 *
 * Identifie automatiquement le courtier et le client dans un enregistrement
 * et extrait uniquement les segments du client pour transcription
 */
class DiarizationService
{
    /**
     * Effectue la diarisation d'un fichier audio
     *
     * @param string $audioPath Chemin complet vers le fichier audio
     * @return array{success: bool, client_segments: array, stats: array, error?: string}
     */
    /**
     * Vérifie si pyannote est disponible et fonctionnel
     */
    public function isAvailable(): bool
    {
        static $available = null;

        if ($available !== null) {
            return $available;
        }

        // Vérifier si Python et pyannote sont disponibles
        exec('python3 -c "import pyannote.audio" 2>&1', $output, $returnCode);
        $available = ($returnCode === 0);

        if (!$available) {
            Log::warning('[DIARIZATION] Pyannote non disponible - diarisation désactivée', [
                'output' => implode("\n", $output)
            ]);
        }

        return $available;
    }

    public function diarize(string $audioPath): array
    {
        // Vérifier si pyannote est disponible
        if (!$this->isAvailable()) {
            Log::info('[DIARIZATION] Pyannote non disponible - fallback sur transcription complète');
            return [
                'success' => false,
                'client_segments' => [],
                'error' => 'Pyannote non disponible',
                'fallback' => true
            ];
        }

        if (!file_exists($audioPath)) {
            Log::error('[DIARIZATION] Fichier audio introuvable', ['path' => $audioPath]);
            return [
                'success' => false,
                'client_segments' => [],
                'error' => 'Fichier audio introuvable'
            ];
        }

        Log::info('🎙️ [DIARIZATION] Début de la diarisation', [
            'audio_path' => $audioPath,
            'file_size' => filesize($audioPath)
        ]);

        try {
            // Créer un fichier temporaire pour les résultats JSON
            $outputJson = storage_path('app/temp/diarization_' . uniqid() . '.json');

            // Créer le dossier temp s'il n'existe pas
            $tempDir = storage_path('app/temp');
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            // Construire la commande Python avec les variables d'environnement
            $scriptPath = base_path('scripts/diarize_audio.py');
            $hfToken = config('services.huggingface.token') ?: env('HUGGINGFACE_TOKEN');

            // Passer le token HuggingFace via variable d'environnement
            $envPrefix = $hfToken ? "HUGGINGFACE_TOKEN={$hfToken} " : '';

            $command = sprintf(
                '%spython3 %s %s %s 2>&1',
                $envPrefix,
                escapeshellarg($scriptPath),
                escapeshellarg($audioPath),
                escapeshellarg($outputJson)
            );

            Log::info('[DIARIZATION] Commande', ['command' => preg_replace('/HUGGINGFACE_TOKEN=\S+/', 'HUGGINGFACE_TOKEN=***', $command)]);

            // Exécuter la diarisation avec timeout (5 minutes max)
            $timeout = 300; // 5 minutes
            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w']
            ];

            $process = proc_open($command, $descriptors, $pipes);

            if (!is_resource($process)) {
                throw new \Exception('Impossible de démarrer le processus de diarisation');
            }

            // Fermer stdin
            fclose($pipes[0]);

            // Lire stdout et stderr avec timeout
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);

            $output = [];
            $startTime = time();

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

                    Log::error('[DIARIZATION] Timeout dépassé', ['timeout' => $timeout]);
                    return [
                        'success' => false,
                        'client_segments' => [],
                        'error' => "Timeout de diarisation dépassé ({$timeout}s)"
                    ];
                }

                usleep(100000); // 100ms
            }

            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            $output = array_filter(explode("\n", $stdout . $stderr));

            fclose($pipes[1]);
            fclose($pipes[2]);
            $returnCode = proc_close($process);

            // Lire les résultats
            if (!file_exists($outputJson)) {
                Log::error('[DIARIZATION] Fichier de résultats non créé', [
                    'output' => implode("\n", $output),
                    'return_code' => $returnCode
                ]);

                return [
                    'success' => false,
                    'client_segments' => [],
                    'error' => 'Échec de la diarisation: ' . implode("\n", $output)
                ];
            }

            $result = json_decode(file_get_contents($outputJson), true);

            // Nettoyer le fichier temporaire
            @unlink($outputJson);

            if (!$result['success']) {
                Log::error('[DIARIZATION] Échec de la diarisation', [
                    'error' => $result['error'] ?? 'Erreur inconnue'
                ]);

                return $result;
            }

            Log::info('✅ [DIARIZATION] Diarisation réussie', [
                'total_speakers' => $result['total_speakers'] ?? 'N/A',
                'client_segments' => count($result['client_segments']),
                'client_duration' => $result['stats']['client_duration'] ?? 0,
                'courtier_duration' => $result['stats']['courtier_duration'] ?? 0
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('[DIARIZATION] Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'client_segments' => [],
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Extrait les segments audio du client depuis un fichier audio
     *
     * @param string $audioPath Chemin vers l'audio complet
     * @param array $segments Segments du client avec start/end timestamps
     * @return string|null Chemin vers le fichier audio contenant uniquement les segments du client
     */
    public function extractClientAudio(string $audioPath, array $segments): ?string
    {
        if (empty($segments)) {
            return null;
        }

        try {
            // Créer un fichier de sortie temporaire
            $outputPath = storage_path('app/temp/client_audio_' . uniqid() . '.wav');

            // Construire la commande ffmpeg pour extraire et concaténer les segments
            $filterComplex = [];
            $concatInputs = [];

            foreach ($segments as $i => $segment) {
                $start = $segment['start'];
                $duration = $segment['duration'];
                $filterComplex[] = sprintf('[0:a]atrim=start=%F:duration=%F,asetpts=PTS-STARTPTS[a%d]', $start, $duration, $i);
                $concatInputs[] = "[a{$i}]";
            }

            $filterComplex[] = implode('', $concatInputs) . 'concat=n=' . count($segments) . ':v=0:a=1[out]';
            $filterComplexStr = implode(';', $filterComplex);

            $command = sprintf(
                'ffmpeg -i %s -filter_complex %s -map "[out]" %s 2>&1',
                escapeshellarg($audioPath),
                escapeshellarg($filterComplexStr),
                escapeshellarg($outputPath)
            );

            exec($command, $output, $returnCode);

            if ($returnCode !== 0 || !file_exists($outputPath)) {
                Log::error('[DIARIZATION] Échec extraction audio client', [
                    'command' => $command,
                    'output' => implode("\n", $output)
                ]);
                return null;
            }

            Log::info('✅ [DIARIZATION] Audio client extrait', [
                'output_path' => $outputPath,
                'file_size' => filesize($outputPath)
            ]);

            return $outputPath;

        } catch (\Exception $e) {
            Log::error('[DIARIZATION] Exception lors de l\'extraction audio', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Nettoie les fichiers temporaires
     */
    public function cleanup(string $audioPath): void
    {
        if (file_exists($audioPath) && strpos($audioPath, '/temp/') !== false) {
            @unlink($audioPath);
            Log::info('🗑️ [DIARIZATION] Fichier temporaire supprimé', ['path' => $audioPath]);
        }
    }
}
