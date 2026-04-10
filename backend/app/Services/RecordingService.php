<?php

namespace App\Services;

use App\Jobs\FinalizeRecordingJob;
use App\Models\AudioRecord;
use App\Models\RecordingSession;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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
        int $teamId,
        ?int $clientId = null
    ): RecordingSession {
        Log::info("[RECORDING] Stockage du chunk #{$partIndex} pour la session {$sessionId}");

        $session = RecordingSession::firstOrCreate(
            ['session_id' => $sessionId],
            [
                'team_id' => $teamId,
                'user_id' => $userId,
                'client_id' => $clientId,
                'status' => 'recording',
                'started_at' => now(),
            ]
        );

        if ($session->user_id !== $userId) {
            throw new \Exception("Cette session n'appartient pas à l'utilisateur connecté");
        }

        $filename = "{$sessionId}_part_{$partIndex}.webm";
        $path = $audio->storeAs("{$sessionId}", $filename, 'recordings');

        Log::info("[RECORDING] Chunk #{$partIndex} stocke : {$path}");

        $session->update([
            'total_chunks' => max($session->total_chunks, $partIndex + 1),
        ]);

        return $session;
    }

    /**
     * Finalise l'enregistrement : dispatch le job asynchrone et retour immediat
     */
    public function finalizeRecording(string $sessionId, int $userId): RecordingSession
    {
        Log::info("[RECORDING] Finalisation de la session {$sessionId}");

        $session = RecordingSession::where('session_id', $sessionId)
            ->where('user_id', $userId)
            ->firstOrFail();

        $session->update(['status' => 'processing']);

        // Verifier qu'il y a des chunks
        $chunks = $this->getChunksInOrder($sessionId, $session->total_chunks);
        if (empty($chunks)) {
            $session->update(['status' => 'failed']);
            throw new \Exception('Aucun chunk trouve pour cette session');
        }

        // Creer l'AudioRecord en attente (sera rempli par le job)
        $audioRecord = AudioRecord::create([
            'team_id' => $session->team_id,
            'user_id' => $session->user_id,
            'client_id' => $session->client_id,
            'path' => null,
            'transcription' => null,
            'status' => 'pending',
        ]);

        // Sauvegarder l'audio_record_id pour le polling frontend
        $session->update(['audio_record_id' => $audioRecord->id]);

        // Dispatcher le job asynchrone (sur queue 'finalize' = app-server)
        FinalizeRecordingJob::dispatch($session, $audioRecord);

        Log::info("[RECORDING] FinalizeRecordingJob dispatche pour session {$sessionId}, audio #{$audioRecord->id}");

        return $session;
    }

    private function getChunksInOrder(string $sessionId, int $totalChunks): array
    {
        $chunks = [];
        for ($i = 0; $i < $totalChunks; $i++) {
            $filename = "{$sessionId}_part_{$i}.webm";
            $path = "{$sessionId}/{$filename}";

            if (Storage::disk('recordings')->exists($path)) {
                $chunks[$i] = Storage::disk('recordings')->path($path);
            } else {
                Log::warning("[RECORDING] Chunk manquant : {$filename}");
            }
        }

        return $chunks;
    }
}
