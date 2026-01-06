<?php

namespace App\Services;

use App\Jobs\ProcessAudioRecording;
use App\Models\AudioRecord;
use App\Models\RecordingSession;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Recording Service
 *
 * Gère la logique métier pour les enregistrements longs (jusqu'à 2h)
 * avec découpage automatique en chunks de 10min max
 */
class RecordingService
{
    private DiarizationService $diarizationService;

    public function __construct(DiarizationService $diarizationService)
    {
        $this->diarizationService = $diarizationService;
    }

    /**
     * Stocke un chunk audio
     */
    public function storeChunk(
        string $sessionId,
        int $partIndex,
        UploadedFile $audio,
        int $userId,
        int $teamId, // Added teamId
        ?int $clientId = null
    ): RecordingSession {
        Log::info("🎙️ [RECORDING] Stockage du chunk #{$partIndex} pour la session {$sessionId}");

        // Récupérer ou créer la session
        $session = RecordingSession::firstOrCreate(
            ['session_id' => $sessionId],
            [
                'team_id' => $teamId, // Added team_id
                'user_id' => $userId,
                'client_id' => $clientId,
                'status' => 'recording',
                'started_at' => now(),
            ]
        );

        // Vérifier que la session appartient bien à l'utilisateur
        if ($session->user_id !== $userId) {
            throw new \Exception("Cette session n'appartient pas à l'utilisateur connecté");
        }

        // Stocker le fichier audio
        $filename = "{$sessionId}_part_{$partIndex}.webm";
        $path = $audio->storeAs("recordings/{$sessionId}", $filename);

        Log::info("✅ [RECORDING] Chunk #{$partIndex} stocké : {$path}");

        // Mettre à jour le nombre de chunks
        $session->update([
            'total_chunks' => max($session->total_chunks, $partIndex + 1),
        ]);

        return $session;
    }

    /**
     * Finalise l'enregistrement : transcrit tous les chunks et concatène
     */
    public function finalizeRecording(string $sessionId, int $userId): RecordingSession
    {
        Log::info("🎬 [RECORDING] Finalisation de la session {$sessionId}");

        $session = RecordingSession::where('session_id', $sessionId)
            ->where('user_id', $userId)
            ->firstOrFail();

        // Marquer comme en cours de traitement
        $session->update(['status' => 'processing']);

        try {
            // Récupérer tous les chunks dans l'ordre
            $chunks = $this->getChunksInOrder($sessionId, $session->total_chunks);

            if (empty($chunks)) {
                throw new \Exception("Aucun chunk trouvé pour cette session");
            }

            Log::info("📂 [RECORDING] {$session->total_chunks} chunks trouvés");

            // Étape 1: Concaténer tous les chunks en un seul fichier audio
            Log::info("🔗 [RECORDING] Concaténation des chunks...");
            $concatenatedAudio = $this->concatenateChunks($chunks, $sessionId);

            // Étape 2: Diarisation pour identifier courtier/client
            Log::info("🎙️ [RECORDING] Diarisation pour séparer courtier/client...");
            $diarizationResult = $this->diarizationService->diarize($concatenatedAudio);

            $finalTranscription = '';

            if ($diarizationResult['success'] && !empty($diarizationResult['client_segments'])) {
                // Diarisation réussie - ne transcrire que les segments du client
                Log::info("✅ [RECORDING] Diarisation réussie - {$diarizationResult['stats']['client_num_segments']} segments client détectés");

                // Extraire l'audio du client uniquement
                $clientAudioPath = $this->diarizationService->extractClientAudio(
                    $concatenatedAudio,
                    $diarizationResult['client_segments']
                );

                if ($clientAudioPath) {
                    // Transcrire uniquement l'audio du client
                    Log::info("🧠 [RECORDING] Transcription des segments client...");
                    $finalTranscription = $this->transcribeChunk($clientAudioPath);

                    // Nettoyer le fichier audio client temporaire
                    $this->diarizationService->cleanup($clientAudioPath);
                } else {
                    Log::warning("⚠️ [RECORDING] Impossible d'extraire l'audio client, transcription complète");
                    $finalTranscription = $this->transcribeChunk($concatenatedAudio);
                }
            } else {
                // Diarisation échouée - transcrire tout l'audio (comportement par défaut)
                Log::warning("⚠️ [RECORDING] Diarisation échouée, transcription de tout l'audio");
                $finalTranscription = $this->transcribeChunk($concatenatedAudio);
            }

            // Nettoyer le fichier audio concaténé
            $this->diarizationService->cleanup($concatenatedAudio);

            Log::info("🎉 [RECORDING] Transcription finale : " . strlen($finalTranscription) . " caractères");

            if (trim($finalTranscription) === '') {
                throw new \Exception("Transcription vide après traitement des chunks");
            }

            // Créer un AudioRecord avec la transcription pour traitement GPT
            $audioRecord = AudioRecord::create([
                'team_id' => $session->team_id, // Added team_id
                'user_id' => $session->user_id,
                'client_id' => $session->client_id,
                'path' => null, // Pas de fichier audio unique, juste la transcription
                'transcription' => $finalTranscription,
                'status' => 'pending',
            ]);

            Log::info("📝 [RECORDING] AudioRecord #{$audioRecord->id} créé pour traitement GPT");

            // Dispatcher le job de traitement GPT
            ProcessAudioRecording::dispatch($audioRecord, $session->client_id);

            // Sauvegarder la transcription finale dans la session
            $session->update([
                'final_transcription' => $finalTranscription,
                'status' => 'completed',
                'finalized_at' => now(),
            ]);

            // Ajouter l'ID de l'AudioRecord à la session pour le polling
            $session->audio_record_id = $audioRecord->id;

            // Nettoyer les chunks
            $this->cleanupChunks($sessionId);

            return $session;
        } catch (\Exception $e) {
            Log::error("❌ [RECORDING] Erreur lors de la finalisation : " . $e->getMessage());

            $session->update(['status' => 'failed']);

            throw $e;
        }
    }

    /**
     * Récupère les chunks dans l'ordre
     */
    private function getChunksInOrder(string $sessionId, int $totalChunks): array
    {
        $chunks = [];
        for ($i = 0; $i < $totalChunks; $i++) {
            $filename = "{$sessionId}_part_{$i}.webm";
            $path = "recordings/{$sessionId}/{$filename}";

            if (Storage::exists($path)) {
                $chunks[$i] = Storage::path($path);
            } else {
                Log::warning("⚠️ [RECORDING] Chunk manquant : {$filename}");
            }
        }
        return $chunks;
    }

    /**
     * Transcrit un chunk via Whisper API OpenAI
     */
    private function transcribeChunk(string $filePath): string
    {
        // Vérifier que le fichier existe
        if (!file_exists($filePath)) {
            throw new \Exception("Fichier audio introuvable : {$filePath}");
        }

        // Vérifier la taille du fichier (minimum 1KB pour éviter les fichiers vides)
        $fileSize = filesize($filePath);
        if ($fileSize < 1024) {
            Log::warning("⚠️ [RECORDING] Fichier trop petit ({$fileSize} bytes), ignoré");
            return ''; // Retourner une chaîne vide pour les fichiers trop petits
        }

        Log::info("📊 [RECORDING] Taille du fichier : " . round($fileSize / 1024, 2) . " KB");

        $apiKey = config('services.openai.api_key');

        if (!$apiKey) {
            throw new \Exception("Clé API OpenAI non configurée");
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
        ])
            ->attach('file', file_get_contents($filePath), basename($filePath))
            ->post('https://api.openai.com/v1/audio/transcriptions', [
                'model' => 'whisper-1',
                'language' => 'fr',
            ]);

        if (!$response->successful()) {
            Log::error("❌ [RECORDING] Erreur Whisper API", [
                'status' => $response->status(),
                'body' => $response->body(),
                'file_size' => $fileSize,
                'file_path' => $filePath,
            ]);
            throw new \Exception("Erreur lors de la transcription Whisper");
        }

        return $response->json('text', '');
    }

    /**
     * Concatène tous les chunks en un seul fichier audio
     */
    private function concatenateChunks(array $chunks, string $sessionId): string
    {
        if (count($chunks) === 1) {
            // Un seul chunk - copier vers temp pour un nettoyage uniforme
            $tempDir = storage_path('app/temp');
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $outputPath = $tempDir . '/concatenated_' . $sessionId . '.webm';
            copy($chunks[0], $outputPath);

            Log::info('✅ [RECORDING] Chunk unique copié vers temp', [
                'output_path' => $outputPath
            ]);

            return $outputPath;
        }

        // Créer un fichier de concaténation temporaire
        $outputPath = storage_path('app/temp/concatenated_' . $sessionId . '.wav');

        // Créer le dossier temp s'il n'existe pas
        $tempDir = storage_path('app/temp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        // Créer un fichier de liste pour ffmpeg
        $fileListPath = $tempDir . '/filelist_' . $sessionId . '.txt';
        $fileListContent = '';

        foreach ($chunks as $chunkPath) {
            // ffmpeg nécessite le format: file '/path/to/file.webm'
            $fileListContent .= "file '" . str_replace("'", "'\\''", $chunkPath) . "'\n";
        }

        file_put_contents($fileListPath, $fileListContent);

        // Concaténer avec ffmpeg
        $command = sprintf(
            'ffmpeg -f concat -safe 0 -i %s -c copy %s 2>&1',
            escapeshellarg($fileListPath),
            escapeshellarg($outputPath)
        );

        exec($command, $output, $returnCode);

        // Nettoyer le fichier de liste
        @unlink($fileListPath);

        if ($returnCode !== 0 || !file_exists($outputPath)) {
            Log::error('[RECORDING] Échec de la concaténation', [
                'command' => $command,
                'output' => implode("\n", $output)
            ]);
            throw new \Exception('Échec de la concaténation des chunks');
        }

        Log::info('✅ [RECORDING] Chunks concaténés', [
            'output_path' => $outputPath,
            'file_size' => filesize($outputPath)
        ]);

        return $outputPath;
    }

    /**
     * Nettoie les chunks après finalisation
     */
    private function cleanupChunks(string $sessionId): void
    {
        $directory = "recordings/{$sessionId}";

        if (Storage::exists($directory)) {
            Storage::deleteDirectory($directory);
            Log::info("🗑️ [RECORDING] Chunks supprimés pour la session {$sessionId}");
        }
    }
}
