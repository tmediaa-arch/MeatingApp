<?php

declare(strict_types=1);

namespace App\Domains\Exports\Jobs;

use App\Domains\Exports\Models\ExportJob;
use App\Domains\Exports\Services\ExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 1800;
    public int $backoff = 60;

    public function __construct(public readonly int $exportJobId)
    {
    }

    public function handle(ExportService $service): void
    {
        $job = ExportJob::findOrFail($this->exportJobId);
        $service->process($job);
    }

    public function failed(\Throwable $e): void
    {
        $job = ExportJob::find($this->exportJobId);
        if ($job && !$job->status->isTerminal()) {
            $job->markFailed($e->getMessage());
        }
    }
}
