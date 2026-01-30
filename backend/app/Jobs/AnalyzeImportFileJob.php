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

class AnalyzeImportFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $timeout = 120;

    public $backoff = [10, 30, 60];

    protected ImportSession $session;

    public function __construct(ImportSession $session)
    {
        $this->session = $session;
    }

    public function handle(ImportOrchestrationService $orchestrator): void
    {
        Log::info('AnalyzeImportFileJob started', [
            'session_id' => $this->session->id,
            'filename' => $this->session->original_filename,
        ]);

        try {
            $orchestrator->analyzeFile($this->session);

            Log::info('AnalyzeImportFileJob completed', [
                'session_id' => $this->session->id,
                'detected_columns' => count($this->session->detected_columns ?? []),
                'total_rows' => $this->session->total_rows,
            ]);
        } catch (\Exception $e) {
            Log::error('AnalyzeImportFileJob failed', [
                'session_id' => $this->session->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('AnalyzeImportFileJob permanently failed', [
            'session_id' => $this->session->id,
            'error' => $exception->getMessage(),
        ]);

        $this->session->update([
            'status' => ImportSession::STATUS_FAILED,
            'errors_summary' => ['job_error' => $exception->getMessage()],
        ]);
    }
}
