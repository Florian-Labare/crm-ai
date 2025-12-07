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

            // Transcrire chaque chunk via Whisper
            $transcriptions = [];
            foreach ($chunks as $index => $chunkPath) {
                Log::info("🧠 [RECORDING] Transcription du chunk #{$index}...");
                $transcription = $this->transcribeChunk($chunkPath);
                $transcriptions[] = $transcription;
                Log::info("✅ [RECORDING] Chunk #{$index} transcrit : " . strlen($transcription) . " caractères");
            }

            // Concaténer toutes les transcriptions
            $finalTranscription = implode(' ', $transcriptions);

            Log::info("🎉 [RECORDING] Transcription finale : " . strlen($finalTranscription) . " caractères");

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
