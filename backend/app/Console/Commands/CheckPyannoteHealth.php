<?php

namespace App\Console\Commands;

use App\Services\PyannoteHealthService;
use Illuminate\Console\Command;

class CheckPyannoteHealth extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'pyannote:health
                            {--refresh : Force a fresh check instead of using cache}
                            {--json : Output as JSON}';

    /**
     * The console command description.
     */
    protected $description = 'Check the health status of the Pyannote diarization system';

    /**
     * Execute the console command.
     */
    public function handle(PyannoteHealthService $healthService): int
    {
        $refresh = $this->option('refresh');
        $json = $this->option('json');

        $this->info('Checking Pyannote health status...');

        $status = $refresh
            ? $healthService->refresh()
            : $healthService->check();

        if ($json) {
            $this->line(json_encode($status, JSON_PRETTY_PRINT));
            return $status['available'] ? Command::SUCCESS : Command::FAILURE;
        }

        // Affichage formaté
        $this->newLine();

        if ($status['available']) {
            $this->info('✅ Pyannote is AVAILABLE and ready for diarization');
        } else {
            $this->error('❌ Pyannote is NOT AVAILABLE');
        }

        $this->newLine();

        // Afficher les checks
        $this->info('Checks:');
        foreach ($status['checks'] ?? [] as $name => $check) {
            $icon = match ($check['status']) {
                'ok' => '✓',
                'warning' => '⚠',
                'error' => '✗',
                default => '?'
            };

            $color = match ($check['status']) {
                'ok' => 'green',
                'warning' => 'yellow',
                'error' => 'red',
                default => 'white'
            };

            $this->line(sprintf(
                '  <fg=%s>%s</> %s: %s',
                $color,
                $icon,
                ucfirst(str_replace('_', ' ', $name)),
                $check['message']
            ));
        }

        // Afficher les warnings
        if (!empty($status['warnings'])) {
            $this->newLine();
            $this->warn('Warnings:');
            foreach ($status['warnings'] as $warning) {
                $this->line("  ⚠ {$warning}");
            }
        }

        // Afficher les erreurs
        if (!empty($status['errors'])) {
            $this->newLine();
            $this->error('Errors:');
            foreach ($status['errors'] as $error) {
                $this->line("  ✗ {$error}");
            }
        }

        $this->newLine();
        $this->line('Checked at: ' . ($status['checked_at'] ?? 'unknown'));

        return $status['available'] ? Command::SUCCESS : Command::FAILURE;
    }
}
