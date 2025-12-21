<?php

namespace App\Services;

use App\Models\DiarizationLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service de monitoring de la diarisation
 *
 * Collecte et analyse les métriques de performance et d'erreurs
 * pour assurer la fiabilité du système de séparation des locuteurs
 */
class DiarizationMonitoringService
{
    /**
     * Enregistre le résultat d'une diarisation
     */
    public function logResult(array $data): DiarizationLog
    {
        $log = DiarizationLog::create($data);

        // Logger les échecs pour alerte
        if (in_array($data['status'], ['failed', 'timeout'])) {
            Log::error('[DIARIZATION MONITORING] Échec de diarisation', [
                'log_id' => $log->id,
                'status' => $data['status'],
                'error' => $data['error_message'] ?? 'Unknown error',
                'audio_record_id' => $data['audio_record_id'] ?? null
            ]);
        }

        return $log;
    }

    /**
     * Enregistre une diarisation réussie
     */
    public function logSuccess(
        ?int $audioRecordId,
        ?int $recordingSessionId,
        ?int $teamId,
        ?int $userId,
        array $diarizationResult,
        int $durationMs,
        ?int $audioDurationSeconds = null,
        ?int $fileSizeBytes = null
    ): DiarizationLog {
        return $this->logResult([
            'audio_record_id' => $audioRecordId,
            'recording_session_id' => $recordingSessionId,
            'team_id' => $teamId,
            'user_id' => $userId,
            'status' => 'success',
            'duration_ms' => $durationMs,
            'audio_duration_seconds' => $audioDurationSeconds,
            'file_size_bytes' => $fileSizeBytes,
            'speakers_detected' => $diarizationResult['total_speakers'] ?? null,
            'broker_speaker_id' => $diarizationResult['courtier_speaker'] ?? null,
            'client_speakers' => $diarizationResult['client_speakers'] ?? [],
            'broker_duration_seconds' => $diarizationResult['stats']['courtier_duration'] ?? null,
            'client_duration_seconds' => $diarizationResult['stats']['client_duration'] ?? null,
            'broker_segments_count' => $diarizationResult['stats']['courtier_num_segments'] ?? null,
            'client_segments_count' => $diarizationResult['stats']['client_num_segments'] ?? null,
            'single_speaker_mode' => $diarizationResult['single_speaker_mode'] ?? false,
            'model_version' => 'pyannote/speaker-diarization-3.1',
            'raw_output' => $diarizationResult,
        ]);
    }

    /**
     * Enregistre un échec de diarisation
     */
    public function logFailure(
        ?int $audioRecordId,
        ?int $recordingSessionId,
        ?int $teamId,
        ?int $userId,
        string $status,
        string $errorMessage,
        ?string $errorCode = null,
        ?int $durationMs = null,
        ?int $audioDurationSeconds = null,
        ?int $fileSizeBytes = null
    ): DiarizationLog {
        return $this->logResult([
            'audio_record_id' => $audioRecordId,
            'recording_session_id' => $recordingSessionId,
            'team_id' => $teamId,
            'user_id' => $userId,
            'status' => $status,
            'error_message' => $errorMessage,
            'error_code' => $errorCode,
            'duration_ms' => $durationMs,
            'audio_duration_seconds' => $audioDurationSeconds,
            'file_size_bytes' => $fileSizeBytes,
        ]);
    }

    /**
     * Récupère les statistiques de diarisation sur une période
     */
    public function getStats(int $days = 7): array
    {
        $startDate = now()->subDays($days);

        $totals = DiarizationLog::withoutGlobalScopes()
            ->where('created_at', '>=', $startDate)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = "success" THEN 1 ELSE 0 END) as success_count,
                SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed_count,
                SUM(CASE WHEN status = "timeout" THEN 1 ELSE 0 END) as timeout_count,
                SUM(CASE WHEN status = "fallback" THEN 1 ELSE 0 END) as fallback_count,
                SUM(CASE WHEN status = "skipped" THEN 1 ELSE 0 END) as skipped_count,
                AVG(duration_ms) as avg_duration_ms,
                AVG(CASE WHEN status = "success" THEN duration_ms END) as avg_success_duration_ms,
                AVG(speakers_detected) as avg_speakers,
                SUM(CASE WHEN single_speaker_mode = 1 THEN 1 ELSE 0 END) as single_speaker_count
            ')
            ->first();

        $successRate = $totals->total > 0
            ? round(($totals->success_count / $totals->total) * 100, 1)
            : 0;

        // Statistiques par jour
        $dailyStats = DiarizationLog::withoutGlobalScopes()
            ->where('created_at', '>=', $startDate)
            ->selectRaw('
                DATE(created_at) as date,
                COUNT(*) as total,
                SUM(CASE WHEN status = "success" THEN 1 ELSE 0 END) as success_count,
                SUM(CASE WHEN status IN ("failed", "timeout") THEN 1 ELSE 0 END) as failure_count
            ')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        // Top erreurs
        $topErrors = DiarizationLog::withoutGlobalScopes()
            ->where('created_at', '>=', $startDate)
            ->whereIn('status', ['failed', 'timeout'])
            ->selectRaw('error_message, error_code, COUNT(*) as count')
            ->groupBy('error_message', 'error_code')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        return [
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => now()->toDateString(),
                'days' => $days
            ],
            'totals' => [
                'total' => (int) $totals->total,
                'success' => (int) $totals->success_count,
                'failed' => (int) $totals->failed_count,
                'timeout' => (int) $totals->timeout_count,
                'fallback' => (int) $totals->fallback_count,
                'skipped' => (int) $totals->skipped_count,
            ],
            'rates' => [
                'success_rate' => $successRate,
                'failure_rate' => round(100 - $successRate, 1),
                'single_speaker_rate' => $totals->total > 0
                    ? round(($totals->single_speaker_count / $totals->total) * 100, 1)
                    : 0
            ],
            'performance' => [
                'avg_duration_ms' => round($totals->avg_duration_ms ?? 0),
                'avg_success_duration_ms' => round($totals->avg_success_duration_ms ?? 0),
                'avg_speakers_detected' => round($totals->avg_speakers ?? 0, 1)
            ],
            'daily' => $dailyStats,
            'top_errors' => $topErrors
        ];
    }

    /**
     * Récupère les échecs récents pour investigation
     */
    public function getRecentFailures(int $limit = 10): array
    {
        return DiarizationLog::withoutGlobalScopes()
            ->whereIn('status', ['failed', 'timeout'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'status' => $log->status,
                    'error_message' => $log->error_message,
                    'error_code' => $log->error_code,
                    'audio_record_id' => $log->audio_record_id,
                    'duration_ms' => $log->duration_ms,
                    'audio_duration_seconds' => $log->audio_duration_seconds,
                    'created_at' => $log->created_at->toISOString()
                ];
            })
            ->toArray();
    }

    /**
     * Génère un résumé de santé du système
     */
    public function getHealthSummary(): array
    {
        // Stats des dernières 24h
        $last24h = DiarizationLog::withoutGlobalScopes()
            ->where('created_at', '>=', now()->subHours(24))
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = "success" THEN 1 ELSE 0 END) as success_count,
                SUM(CASE WHEN status IN ("failed", "timeout") THEN 1 ELSE 0 END) as failure_count
            ')
            ->first();

        $successRate24h = $last24h->total > 0
            ? ($last24h->success_count / $last24h->total) * 100
            : 100;

        // Déterminer le statut de santé
        $status = 'healthy';
        $message = 'Diarization system operating normally';

        if ($last24h->total === 0) {
            $status = 'unknown';
            $message = 'No diarization activity in the last 24 hours';
        } elseif ($successRate24h < 50) {
            $status = 'critical';
            $message = 'High failure rate - immediate attention required';
        } elseif ($successRate24h < 80) {
            $status = 'degraded';
            $message = 'Elevated failure rate - investigation recommended';
        } elseif ($successRate24h < 95) {
            $status = 'warning';
            $message = 'Some failures detected - monitoring advised';
        }

        // Vérifier les échecs consécutifs
        $lastLogs = DiarizationLog::withoutGlobalScopes()
            ->orderByDesc('created_at')
            ->limit(5)
            ->pluck('status')
            ->toArray();

        $consecutiveFailures = 0;
        foreach ($lastLogs as $logStatus) {
            if (in_array($logStatus, ['failed', 'timeout'])) {
                $consecutiveFailures++;
            } else {
                break;
            }
        }

        if ($consecutiveFailures >= 3) {
            $status = 'critical';
            $message = "Last {$consecutiveFailures} diarizations failed consecutively";
        }

        return [
            'status' => $status,
            'message' => $message,
            'last_24h' => [
                'total' => (int) $last24h->total,
                'success' => (int) $last24h->success_count,
                'failures' => (int) $last24h->failure_count,
                'success_rate' => round($successRate24h, 1)
            ],
            'consecutive_failures' => $consecutiveFailures,
            'checked_at' => now()->toISOString()
        ];
    }
}
