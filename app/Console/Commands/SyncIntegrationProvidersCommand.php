<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Integrations\Jobs\RunIntegrationSyncJob;
use App\Domains\Integrations\Models\IntegrationProvider;
use Cron\CronExpression;
use Illuminate\Console\Command;

/**
 * Phase 6 — Trigger کردن sync providers که due هستند.
 *
 * این command هر دقیقه اجرا می‌شود و provider هایی که next_sync_at آن‌ها
 * گذشته است را در صف می‌گذارد.
 */
class SyncIntegrationProvidersCommand extends Command
{
    protected $signature = 'integrations:sync-due';
    protected $description = 'trigger کردن sync providers که due هستند';

    public function handle(): int
    {
        $due = IntegrationProvider::query()->due()->get();

        if ($due->isEmpty()) {
            $this->info('هیچ provider due ای پیدا نشد.');
            return self::SUCCESS;
        }

        foreach ($due as $provider) {
            try {
                RunIntegrationSyncJob::dispatch($provider->id, 'scheduled', null);

                // محاسبه next_sync_at بعدی بر اساس cron
                if ($provider->sync_schedule) {
                    try {
                        $cron = new CronExpression($provider->sync_schedule);
                        $provider->forceFill([
                            'next_sync_at' => $cron->getNextRunDate(),
                        ])->save();
                    } catch (\Throwable $e) {
                        $this->error("Provider #{$provider->id} cron نامعتبر: {$e->getMessage()}");
                    }
                }

                $this->info("✓ Provider {$provider->display_name} در صف sync قرار گرفت.");
            } catch (\Throwable $e) {
                $this->error("✗ Provider #{$provider->id} ناموفق: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
