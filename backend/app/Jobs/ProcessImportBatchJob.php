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

class ProcessImportBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $timeout = 300;

    public $backoff = [10, 30, 60];

    protected ImportSession $session;

    protected int $offset;

    protected int $limit;

    public function __construct(ImportSession $session, int $offset, int $limit = 50)
    {
        $this->session = $session;
        $this->offset = $offset;
        $this->limit = $limit;
    }

    public function handle(ImportOrchestrationService $orchestrator): void
    {
        Log::info('ProcessImportBatchJob started', [
            'session_id' => $this->session->id,
            'offset' => $this->offset,
            'limit' => $this->limit,
        ]);

        try {
            $results = $orchestrator->processBatch($this->session, $this->offset, $this->limit);

            Log::info('ProcessImportBatchJob completed', [
                'session_id' => $this->session->id,
                'offset' => $this->offset,
                'results' => $results,
            ]);
        } catch (\Exception $e) {
            Log::error('ProcessImportBatchJob failed', [
                'session_id' => $this->session->id,
                'offset' => $this->offset,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessImportBatchJob permanently failed', [
            'session_id' => $this->session->id,
            'offset' => $this->offset,
            'error' => $exception->getMessage(),
        ]);

        $errorsSummary = $this->session->errors_summary ?? [];
        $errorsSummary["batch_{$this->offset}"] = $exception->getMessage();

        $this->session->update([
            'errors_summary' => $errorsSummary,
        ]);
    }
}
