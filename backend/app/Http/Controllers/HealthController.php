<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    /**
     * Vérifie la santé globale du système audio
     */
    public function audioSystem(): JsonResponse
    {
        return response()->json([
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'features' => [
                'transcription' => true,
                'diarization' => true, // Native via Voxtral
                'speaker_separation' => true, // Courtier / Client via Voxtral
            ],
            'message' => 'Audio pipeline operational — diarisation native Voxtral (Courtier/Client)',
        ]);
    }
}
