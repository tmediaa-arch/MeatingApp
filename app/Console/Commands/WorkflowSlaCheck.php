<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Workflow\Jobs\WorkflowSlaCheckerJob;
use Illuminate\Console\Command;

class WorkflowSlaCheck extends Command
{
    protected $signature = 'workflow:sla-check';

    protected $description = 'بررسی instanceهای از SLA رد شده و ایجاد incident';

    public function handle(): int
    {
        $this->info('Workflow SLA check started');
        (new WorkflowSlaCheckerJob())->handle();
        $this->info('Workflow SLA check completed');
        return self::SUCCESS;
    }
}
