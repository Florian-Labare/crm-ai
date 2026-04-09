<?php

namespace App\Http\Controllers;

use App\Http\Requests\FinalizeRecordingRequest;
use App\Http\Requests\StoreChunkRequest;
use App\Services\RecordingService;
use Illuminate\Http\JsonResponse;

/**
 * Recording Controller
 *
 * Gère les enregistrements longs (jusqu'à 2h) avec découpage en chunks
 */
class RecordingController extends Controller
{
    public function __construct(
        private readonly RecordingService $recordingService
    ) {
    }

    /**
     * Stocke un chunk audio
     */
    public function storeChunk(StoreChunkRequest $request): JsonResponse
    {
        try {
            $session = $this->recordingService->storeChunk(
                sessionId: $request->input('session_id'),
                partIndex: $request->input('part_index'),
                audio: $request->file('audio'),
                userId: auth()->id(),
                teamId: auth()->user()->currentTeam()->id, // Pass team_id
                clientId: $request->input('client_id')
            );

            return response()->json([
                'success' => true,
                'message' => 'Chunk enregistré avec succès',
                'session' => [
                    'session_id' => $session->session_id,
                    'total_chunks' => $session->total_chunks,
                    'status' => $session->status,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'enregistrement du chunk',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Finalise l'enregistrement et retourne la transcription complète
     */
    public function finalize(string $sessionId, FinalizeRecordingRequest $request): JsonResponse
    {
        try {
            $session = $this->recordingService->finalizeRecording(
                sessionId: $sessionId,
                userId: auth()->id()
            );

            return response()->json([
                'success' => true,
                'message' => 'Transcription terminée, traitement IA en cours...',
                'audio_record_id' => $session->audio_record_id,
                'transcription' => $session->final_transcription,
                'session' => [
                    'session_id' => $session->session_id,
                    'total_chunks' => $session->total_chunks,
                    'status' => $session->status,
                    'finalized_at' => $session->finalized_at?->toISOString(),
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la finalisation',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
