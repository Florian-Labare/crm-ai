<?php

namespace App\Services;

use App\Models\AudioRecord;
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
    private ?DiarizationMonitoringService $monitoringService = null;

    public function __construct(?DiarizationMonitoringService $monitoringService = null)
    {
        $this->monitoringService = $monitoringService ?? app(DiarizationMonitoringService::class);
    }

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

    /**
     * Effectue la diarisation avec monitoring et stockage des résultats
     *
     * @param string $audioPath Chemin vers le fichier audio
     * @param array $context Contexte optionnel (audio_record_id, team_id, user_id, etc.)
     * @return array
     */
    public function diarizeWithMonitoring(string $audioPath, array $context = []): array
    {
        $startTime = microtime(true);
        $fileSize = file_exists($audioPath) ? filesize($audioPath) : null;

        $result = $this->diarize($audioPath);

        $durationMs = (int) ((microtime(true) - $startTime) * 1000);

        // Logger le résultat
        if ($this->monitoringService) {
            if ($result['success']) {
                $this->monitoringService->logSuccess(
                    audioRecordId: $context['audio_record_id'] ?? null,
                    recordingSessionId: $context['recording_session_id'] ?? null,
                    teamId: $context['team_id'] ?? null,
                    userId: $context['user_id'] ?? null,
                    diarizationResult: $result,
                    durationMs: $durationMs,
                    audioDurationSeconds: $context['audio_duration_seconds'] ?? null,
                    fileSizeBytes: $fileSize
                );
            } elseif ($result['fallback'] ?? false) {
                $this->monitoringService->logFailure(
                    audioRecordId: $context['audio_record_id'] ?? null,
                    recordingSessionId: $context['recording_session_id'] ?? null,
                    teamId: $context['team_id'] ?? null,
                    userId: $context['user_id'] ?? null,
                    status: 'fallback',
                    errorMessage: $result['error'] ?? 'Pyannote unavailable',
                    durationMs: $durationMs,
                    fileSizeBytes: $fileSize
                );
            } else {
                $this->monitoringService->logFailure(
                    audioRecordId: $context['audio_record_id'] ?? null,
                    recordingSessionId: $context['recording_session_id'] ?? null,
                    teamId: $context['team_id'] ?? null,
                    userId: $context['user_id'] ?? null,
                    status: 'failed',
                    errorMessage: $result['error'] ?? 'Unknown error',
                    durationMs: $durationMs,
                    fileSizeBytes: $fileSize
                );
            }
        }

        return $result;
    }

    /**
     * Met à jour un AudioRecord avec les résultats de diarisation
     */
    public function updateAudioRecordWithDiarization(AudioRecord $audioRecord, array $diarizationResult): void
    {
        $audioRecord->update([
            'diarization_data' => $diarizationResult,
            'diarization_success' => $diarizationResult['success'] ?? false,
        ]);
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
            $outputJson = storage_path('app/temp/diarization_' . bin2hex(random_bytes(8)) . '.json');

            // Créer le dossier temp s'il n'existe pas
            $tempDir = storage_path('app/temp');
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            // Construire la commande Python
            $scriptPath = base_path('scripts/diarize_audio.py');

            // SECURITE: Ne pas passer le token dans la ligne de commande (visible dans ps aux)
            // Utiliser proc_open avec le paramètre env pour passer les variables d'environnement
            $command = sprintf(
                'python3 %s %s %s',
                escapeshellarg($scriptPath),
                escapeshellarg($audioPath),
                escapeshellarg($outputJson)
            );

            Log::info('[DIARIZATION] Commande', ['command' => $command]);

            // Préparer l'environnement sécurisé (token non visible dans ps aux)
            $hfToken = config('services.huggingface.token') ?: env('HUGGINGFACE_TOKEN');
            $processEnv = array_merge($_ENV, $_SERVER, [
                'HUGGINGFACE_TOKEN' => $hfToken ?? '',
                'HOME' => $_SERVER['HOME'] ?? '/tmp',
                'PATH' => $_SERVER['PATH'] ?? '/usr/local/bin:/usr/bin:/bin',
            ]);
            // Nettoyer les variables qui ne sont pas des strings
            $processEnv = array_filter($processEnv, fn($v) => is_string($v));

            // Exécuter la diarisation avec timeout (5 minutes max)
            $timeout = 300; // 5 minutes
            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w']
            ];

            // SECURITE: Passer l'environnement via le 5ème paramètre de proc_open
            $process = proc_open($command, $descriptors, $pipes, null, $processEnv);

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
