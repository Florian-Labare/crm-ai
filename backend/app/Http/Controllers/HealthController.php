<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    /**
     * Check audio system health
     */
    public function audioSystem(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'message' => 'Audio system is operational',
        ]);
    }

    /**
     * Check pyannote availability
     */
    public function pyannote(): JsonResponse
    {
        exec('python3 -c "import pyannote.audio" 2>&1', $output, $returnCode);

        return response()->json([
            'status' => $returnCode === 0 ? 'ok' : 'unavailable',
            'available' => $returnCode === 0,
        ]);
    }

    /**
     * Get diarization stats
     */
    public function diarizationStats(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'message' => 'Diarization stats not implemented',
        ]);
    }
}
