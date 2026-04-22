<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Tasks\Services\TaskEscalationService;
use Illuminate\Console\Command;

class RunTaskEscalations extends Command
{
    protected $signature = 'tasks:escalate';

    protected $description = 'شناسایی وظایف تأخیردار و انجام escalation سطح‌به‌سطح';

    public function handle(TaskEscalationService $service): int
    {
        $this->info('شروع escalation وظایف...');

        $result = $service->runDaily();

        $this->info(sprintf(
            '✓ %d وظیفه به‌عنوان overdue علامت‌گذاری شد، %d وظیفه escalate شد.',
            $result['marked_overdue'],
            $result['escalated'],
        ));

        return Command::SUCCESS;
    }
}
