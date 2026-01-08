<?php

namespace App\Console\Commands;

use App\Services\DiarizationMonitoringService;
use Illuminate\Console\Command;

class DiarizationStats extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'diarization:stats
                            {--days=7 : Number of days to analyze}
                            {--json : Output as JSON}';

    /**
     * The console command description.
     */
    protected $description = 'Display diarization statistics and health summary';

    /**
     * Execute the console command.
     */
    public function handle(DiarizationMonitoringService $monitoringService): int
    {
        $days = (int) $this->option('days');
        $json = $this->option('json');

        $stats = $monitoringService->getStats($days);
        $healthSummary = $monitoringService->getHealthSummary();
        $recentFailures = $monitoringService->getRecentFailures(5);

        if ($json) {
            $this->line(json_encode([
                'stats' => $stats,
                'health_summary' => $healthSummary,
                'recent_failures' => $recentFailures,
            ], JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        // Health Summary
        $this->newLine();
        $statusColor = match ($healthSummary['status']) {
            'healthy' => 'green',
            'warning' => 'yellow',
            'degraded' => 'yellow',
            'critical' => 'red',
            default => 'white'
        };

        $this->line(sprintf(
            '<fg=%s;options=bold>%s - %s</>',
            $statusColor,
            strtoupper($healthSummary['status']),
            $healthSummary['message']
        ));

        // 24h Stats
        $this->newLine();
        $this->info("Last 24 hours:");
        $this->line(sprintf(
            '  Success rate: %s%% (%d/%d)',
            $healthSummary['last_24h']['success_rate'],
            $healthSummary['last_24h']['success'],
            $healthSummary['last_24h']['total']
        ));

        if ($healthSummary['consecutive_failures'] > 0) {
            $this->warn("  ⚠ {$healthSummary['consecutive_failures']} consecutive failures");
        }

        // Period Stats
        $this->newLine();
        $this->info("Statistics for the last {$days} days:");
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total diarizations', $stats['totals']['total']],
                ['Successful', $stats['totals']['success']],
                ['Failed', $stats['totals']['failed']],
                ['Timeouts', $stats['totals']['timeout']],
                ['Fallbacks', $stats['totals']['fallback']],
                ['Success rate', $stats['rates']['success_rate'] . '%'],
                ['Avg duration', round($stats['performance']['avg_success_duration_ms']) . ' ms'],
                ['Avg speakers', $stats['performance']['avg_speakers_detected']],
                ['Single speaker rate', $stats['rates']['single_speaker_rate'] . '%'],
            ]
        );

        // Top Errors
        if (!empty($stats['top_errors'])) {
            $this->newLine();
            $this->error('Top errors:');
            $this->table(
                ['Error', 'Count'],
                array_map(fn($e) => [
                    substr($e['error_message'], 0, 60) . (strlen($e['error_message']) > 60 ? '...' : ''),
                    $e['count']
                ], $stats['top_errors'])
            );
        }

        // Recent Failures
        if (!empty($recentFailures)) {
            $this->newLine();
            $this->warn('Recent failures:');
            foreach ($recentFailures as $failure) {
                $this->line(sprintf(
                    '  [%s] %s - %s',
                    $failure['created_at'],
                    $failure['status'],
                    substr($failure['error_message'], 0, 50)
                ));
            }
        }

        return Command::SUCCESS;
    }
}
