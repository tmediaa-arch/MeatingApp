<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Reports\Services\ReportRegistryService;
use Illuminate\Console\Command;

/**
 * Phase 6 — sync همه گزارش‌های registered به DB.
 *
 * هر بار که گزارش جدیدی به DomainServiceProvider::registerReports() اضافه شد،
 * این command اجرا می‌شود تا metadata مربوطه در DB ایجاد/به‌روزرسانی شود.
 */
class SyncReportRegistryCommand extends Command
{
    protected $signature = 'reports:sync-registry';
    protected $description = 'sync گزارش‌های ثبت‌شده در DomainServiceProvider به DB';

    public function handle(ReportRegistryService $registry): int
    {
        $this->info('در حال sync گزارش‌ها به DB...');
        $count = $registry->syncToDatabase();
        $this->info("تعداد {$count} گزارش sync شد.");
        return self::SUCCESS;
    }
}
