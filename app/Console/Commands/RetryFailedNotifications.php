<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Notifications\Jobs\RetryFailedNotificationsJob;
use Illuminate\Console\Command;

class RetryFailedNotifications extends Command
{
    protected $signature = 'notifications:retry';

    protected $description = 'تلاش مجدد برای ارسال اعلان‌های ناموفق (با respecting backoff)';

    public function handle(): int
    {
        RetryFailedNotificationsJob::dispatch();
        $this->info('Job retry در صف dispatch شد.');
        return Command::SUCCESS;
    }
}
