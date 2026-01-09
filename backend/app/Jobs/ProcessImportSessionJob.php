<?php

namespace App\Jobs;

use App\Models\ImportSession;
use App\Services\Import\ImportOrchestrationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessImportSessionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $timeout = 600;

    public $backoff = [30, 60, 120];

    protected ImportSession $session;

    public function __construct(ImportSession $session)
    {
        $this->session = $session;
    }

    public function handle(ImportOrchestrationService $orchestrator): void
    {
        Log::info('ProcessImportSessionJob started', [
            'session_id' => $this->session->id,
        ]);

        try {
            $orchestrator->processSession($this->session);

            $this->session->refresh();
            $batchSize = 50;
            $totalBatches = ceil($this->session->total_rows / $batchSize);

            for ($i = 0; $i < $totalBatches; $i++) {
                ProcessImportBatchJob::dispatch($this->session, $i * $batchSize, $batchSize)
                    ->delay(now()->addSeconds($i * 2));
            }

            Log::info('ProcessImportSessionJob dispatched batches', [
                'session_id' => $this->session->id,
                'total_batches' => $totalBatches,
            ]);
        } catch (\Exception $e) {
            Log::error('ProcessImportSessionJob failed', [
                'session_id' => $this->session->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessImportSessionJob permanently failed', [
            'session_id' => $this->session->id,
            'error' => $exception->getMessage(),
        ]);

        $this->session->update([
            'status' => ImportSession::STATUS_FAILED,
            'errors_summary' => ['job_error' => $exception->getMessage()],
        ]);
    }
}
