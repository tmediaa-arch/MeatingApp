<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Workflow\Jobs\WorkflowTimerJob;
use Illuminate\Console\Command;

class WorkflowTimerTick extends Command
{
    protected $signature = 'workflow:timer-tick';

    protected $description = 'بیدار کردن tokenهای دارای wait_until گذشته (هر دقیقه)';

    public function handle(): int
    {
        $this->info('Workflow timer tick started');
        (new WorkflowTimerJob())->handle(app(\App\Domains\Workflow\Services\Runtime\WorkflowEngine::class));
        $this->info('Workflow timer tick completed');
        return self::SUCCESS;
    }
}
