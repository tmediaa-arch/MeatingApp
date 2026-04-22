<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Exports\Services\ExportCleanupService;
use Illuminate\Console\Command;

/**
 * Phase 6 — پاک‌سازی export jobs منقضی شده.
 *
 * فایل‌های مربوطه نیز delete می‌شوند تا storage آزاد شود.
 * روزانه اجرا می‌شود.
 */
class CleanupExpiredExportsCommand extends Command
{
    protected $signature = 'exports:cleanup-expired';
    protected $description = 'حذف export jobs منقضی شده و فایل‌های آن‌ها';

    public function handle(ExportCleanupService $cleanup): int
    {
        $this->info('در حال پاک‌سازی...');
        $result = $cleanup->cleanup();
        $jobs = $result['expired_jobs'] ?? 0;
        $files = $result['deleted_files'] ?? 0;
        $this->info("تعداد {$jobs} export منقضی علامت زده شد، {$files} فایل پاک شد.");
        return self::SUCCESS;
    }
}
