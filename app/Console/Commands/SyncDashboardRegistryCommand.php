<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Dashboards\Services\DashboardRegistryService;
use Illuminate\Console\Command;

class SyncDashboardRegistryCommand extends Command
{
    protected $signature = 'dashboards:sync-registry';
    protected $description = 'sync داشبوردهای ثبت‌شده در DomainServiceProvider به DB';

    public function handle(DashboardRegistryService $registry): int
    {
        $this->info('در حال sync داشبوردها به DB...');
        $count = $registry->syncToDatabase();
        $this->info("تعداد {$count} داشبورد sync شد.");
        return self::SUCCESS;
    }
}
