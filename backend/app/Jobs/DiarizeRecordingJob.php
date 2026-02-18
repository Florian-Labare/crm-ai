<?php

namespace App\Jobs;

use App\Models\AudioRecord;
use App\Services\DiarizationService;
use App\Services\TranscriptionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DiarizeRecordingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;
    public $tries = 2;
    public $backoff = [60];

    public function __construct(
        protected AudioRecord $audioRecord,
        protected string $concatenatedAudioPath,
        protected string $sessionId
    ) {
        $this->onQueue('finalize');
    }

    public function handle(
        DiarizationService $diarizationService,
        TranscriptionService $transcriptionService
    ): void {
        Log::info("[DIARIZE] Debut diarisation background pour session {$this->sessionId}, audio #{$this->audioRecord->id}");

        try {
            // 1. Verifier que le fichier concatene existe encore
            if (!file_exists($this->concatenatedAudioPath)) {
                Log::warning("[DIARIZE] Fichier concatene introuvable, abandon", [
                    'path' => $this->concatenatedAudioPath,
                ]);
                $this->audioRecord->update(['diarization_success' => false]);
                return;
            }

            // 2. Diariser avec Pyannote
            Log::info("[DIARIZE] Lancement Pyannote...");
            $diarizationResult = $diarizationService->diarize($this->concatenatedAudioPath);

            // Stocker les resultats de diarisation (meme en cas d'echec)
            $diarizationService->updateAudioRecordWithDiarization($this->audioRecord, $diarizationResult);

            if (!$diarizationResult['success'] || empty($diarizationResult['client_segments'])) {
                Log::warning("[DIARIZE] Diarisation echouee ou pas de segments client", [
                    'success' => $diarizationResult['success'],
                    'error' => $diarizationResult['error'] ?? null,
                ]);
                return;
            }

            Log::info("[DIARIZE] Diarisation reussie - {$diarizationResult['stats']['client_num_segments']} segments client");

            // 3. Extraire l'audio client
            $clientAudioPath = $diarizationService->extractClientAudio(
                $this->concatenatedAudioPath,
                $diarizationResult['client_segments']
            );

            if (!$clientAudioPath) {
                Log::warning("[DIARIZE] Impossible d'extraire l'audio client");
                return;
            }

            // 4. Transcrire les segments client
            Log::info("[DIARIZE] Transcription des segments client...");
            $clientTranscription = $this->transcribeClientAudio($clientAudioPath, $transcriptionService);
            $diarizationService->cleanup($clientAudioPath);

            if (!empty($clientTranscription)) {
                $this->audioRecord->update(['client_transcription' => $clientTranscription]);
                Log::info("[DIARIZE] Transcription client stockee : " . strlen($clientTranscription) . " caracteres");
            }

            Log::info("[DIARIZE] Diarisation terminee avec succes pour session {$this->sessionId}");

        } catch (\Throwable $e) {
            // Ne jamais throw - echec gracieux
            Log::error("[DIARIZE] Erreur inattendue pour session {$this->sessionId}: {$e->getMessage()}", [
                'trace' => $e->getTraceAsString(),
            ]);
            $this->audioRecord->update(['diarization_success' => false]);
        } finally {
            // Toujours cleanup le fichier concatene
            if (file_exists($this->concatenatedAudioPath) && str_contains($this->concatenatedAudioPath, '/temp/')) {
                @unlink($this->concatenatedAudioPath);
                Log::info("[DIARIZE] Fichier concatene supprime", ['path' => $this->concatenatedAudioPath]);
            }
        }
    }

    private function transcribeClientAudio(string $filePath, TranscriptionService $transcriptionService): ?string
    {
        if (!file_exists($filePath)) {
            return null;
        }

        $fileSize = filesize($filePath);
        if ($fileSize < 1024) {
            Log::warning("[DIARIZE] Fichier audio client trop petit ({$fileSize} bytes)");
            return null;
        }

        try {
            return $transcriptionService->transcribe($filePath);
        } catch (\Throwable $e) {
            Log::warning("[DIARIZE] Echec transcription client: {$e->getMessage()}");
            return null;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("[DIARIZE] Job echoue definitivement pour session {$this->sessionId}: {$exception->getMessage()}");
        $this->audioRecord->update(['diarization_success' => false]);
    }
}
