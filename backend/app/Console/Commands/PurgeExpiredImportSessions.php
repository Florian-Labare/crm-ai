<?php

namespace App\Console\Commands;

use App\Services\Import\RgpdComplianceService;
use Illuminate\Console\Command;

class PurgeExpiredImportSessions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:purge-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Purge import sessions that have exceeded their RGPD retention period';

    /**
     * Execute the console command.
     */
    public function handle(RgpdComplianceService $rgpdService): int
    {
        $this->info('Starting purge of expired import sessions...');

        $count = $rgpdService->purgeExpiredSessions();

        $this->info("Purged {$count} expired import session(s).");

        return Command::SUCCESS;
    }
}
