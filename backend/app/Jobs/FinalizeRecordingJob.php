<?php

namespace App\Jobs;

use App\Models\AudioRecord;
use App\Models\RecordingSession;
use App\Services\TranscriptionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FinalizeRecordingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;

    public $tries = 1;

    public function __construct(
        protected RecordingSession $session,
        protected AudioRecord $audioRecord
    ) {
        $this->onQueue('finalize');
    }

    public function handle(
        TranscriptionService $transcriptionService
    ): void {
        $sessionId = $this->session->session_id;

        Log::info("[FINALIZE] Debut du traitement pour session {$sessionId}");

        $this->audioRecord->update(['status' => 'processing']);

        // 1. Recuperer les chunks dans l'ordre (disque local recordings)
        $chunks = $this->getChunksInOrder($sessionId, $this->session->total_chunks);

        if (empty($chunks)) {
            throw new \Exception("Aucun chunk trouve pour la session {$sessionId}");
        }

        Log::info("[FINALIZE] {$this->session->total_chunks} chunks trouves");

        // 2. Concatener avec FFmpeg
        Log::info('[FINALIZE] Concatenation des chunks...');
        $concatenatedAudio = $this->concatenateChunks($chunks, $sessionId);

        try {
            // 3. Transcrire avec diarisation native Voxtral (Courtier / Client)
            Log::info("[FINALIZE] Transcription avec diarisation Voxtral...");
            $result = $transcriptionService->transcribeWithDiarization($concatenatedAudio);

            $finalTranscription = $result['transcript'];
            $clientTranscription = $result['client_transcript'];

            Log::info('[FINALIZE] Transcription finale : '.strlen($finalTranscription).' caracteres', [
                'has_diarization' => $clientTranscription !== null,
                'client_transcript_length' => strlen($clientTranscription ?? ''),
            ]);

            if (trim($finalTranscription) === '') {
                throw new \Exception('Transcription vide apres traitement des chunks');
            }

            // 4. Mettre a jour l'AudioRecord
            $this->audioRecord->update([
                'transcription' => $finalTranscription,
                'client_transcription' => $clientTranscription,
                'status' => 'pending',
            ]);

            // 5. Mettre a jour la session
            $this->session->update([
                'final_transcription' => $finalTranscription,
                'status' => 'completed',
                'finalized_at' => now(),
            ]);

            // 6. Dispatcher ProcessAudioRecording (queue 'audio')
            // On passe la transcription client si disponible, sinon la transcription complète
            ProcessAudioRecording::dispatch($this->audioRecord, $this->session->client_id);
            Log::info("[FINALIZE] ProcessAudioRecording dispatche pour audio #{$this->audioRecord->id}");

            // 7. Cleanup chunks + fichier concatene
            $this->cleanupChunks($sessionId);

        } finally {
            // Toujours nettoyer le fichier concatene
            if (file_exists($concatenatedAudio) && str_contains($concatenatedAudio, '/temp/')) {
                @unlink($concatenatedAudio);
                Log::info('[FINALIZE] Fichier concatene supprime');
            }
        }

        Log::info("[FINALIZE] Session {$sessionId} finalisee avec succes");
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("[FINALIZE] Echec pour session {$this->session->session_id}: {$exception->getMessage()}");

        $this->session->update(['status' => 'failed']);
        $this->audioRecord->update([
            'status' => 'failed',
            'transcription' => 'Echec finalisation : '.$exception->getMessage(),
        ]);
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
                Log::warning("[FINALIZE] Chunk manquant : {$filename}");
            }
        }

        return $chunks;
    }

    private function concatenateChunks(array $chunks, string $sessionId): string
    {
        if (count($chunks) === 1) {
            $tempDir = storage_path('app/temp');
            if (! is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $outputPath = $tempDir.'/concatenated_'.$sessionId.'.webm';
            copy($chunks[0], $outputPath);

            return $outputPath;
        }

        $tempDir = storage_path('app/temp');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $fileListPath = $tempDir.'/filelist_'.$sessionId.'.txt';
        $fileListContent = '';

        foreach ($chunks as $chunkPath) {
            if (! file_exists($chunkPath)) {
                continue;
            }
            $fileListContent .= "file '".str_replace("'", "'\\''", $chunkPath)."'\n";
        }

        if (empty($fileListContent)) {
            throw new \Exception('Aucun chunk valide trouve pour la concatenation');
        }

        file_put_contents($fileListPath, $fileListContent);

        $outputPath = $tempDir.'/concatenated_'.$sessionId.'.ogg';

        $command = sprintf(
            'ffmpeg -y -f concat -safe 0 -i %s -c copy %s 2>&1',
            escapeshellarg($fileListPath),
            escapeshellarg($outputPath)
        );

        exec($command, $output, $returnCode);

        @unlink($fileListPath);

        if ($returnCode !== 0) {
            throw new \Exception('Echec de la concatenation des chunks (ffmpeg error code: '.$returnCode.')');
        }

        if (! file_exists($outputPath) || filesize($outputPath) < 1024) {
            throw new \Exception('Echec de la concatenation des chunks (fichier de sortie invalide)');
        }

        return $outputPath;
    }

    private function cleanupChunks(string $sessionId): void
    {
        if (Storage::disk('recordings')->exists($sessionId)) {
            Storage::disk('recordings')->deleteDirectory($sessionId);
            Log::info("[FINALIZE] Chunks supprimes pour la session {$sessionId}");
        }
    }
}
