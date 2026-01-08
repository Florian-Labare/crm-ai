<?php

namespace App\Http\Controllers;

use App\Services\PyannoteHealthService;
use App\Services\DiarizationMonitoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller pour les endpoints de santé du système
 */
class HealthController extends Controller
{
    public function __construct(
        private readonly PyannoteHealthService $pyannoteHealth,
        private readonly DiarizationMonitoringService $monitoringService
    ) {
    }

    /**
     * Vérifie la santé globale du système audio
     */
    public function audioSystem(): JsonResponse
    {
        $pyannoteStatus = $this->pyannoteHealth->check();

        return response()->json([
            'status' => $pyannoteStatus['available'] ? 'healthy' : 'degraded',
            'timestamp' => now()->toISOString(),
            'components' => [
                'pyannote' => $pyannoteStatus,
            ],
            'features' => [
                'transcription' => true, // Toujours disponible via Whisper
                'diarization' => $pyannoteStatus['available'],
                'speaker_separation' => $pyannoteStatus['available'],
            ],
            'message' => $pyannoteStatus['available']
                ? 'All audio features available'
                : 'Diarization unavailable - transcription will process full audio without speaker separation'
        ]);
    }

    /**
     * Vérifie spécifiquement pyannote
     */
    public function pyannote(Request $request): JsonResponse
    {
        $forceRefresh = $request->boolean('refresh', false);

        $status = $forceRefresh
            ? $this->pyannoteHealth->refresh()
            : $this->pyannoteHealth->check();

        return response()->json([
            'available' => $status['available'],
            'cached' => !$forceRefresh,
            'details' => $status
        ]);
    }

    /**
     * Retourne les statistiques de monitoring de la diarisation
     */
    public function diarizationStats(Request $request): JsonResponse
    {
        $days = $request->input('days', 7);

        $stats = $this->monitoringService->getStats($days);
        $recentFailures = $this->monitoringService->getRecentFailures(10);

        return response()->json([
            'period_days' => $days,
            'stats' => $stats,
            'recent_failures' => $recentFailures,
            'health_summary' => $this->monitoringService->getHealthSummary()
        ]);
    }
}
