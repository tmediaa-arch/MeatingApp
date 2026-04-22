<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Integrations\Models\WebhookDelivery;
use App\Domains\Integrations\Services\WebhookDispatchService;
use Illuminate\Console\Command;

/**
 * Phase 6 — تلاش مجدد برای webhook deliveries که در وضعیت retrying هستند.
 *
 * هر 5 دقیقه اجرا می‌شود.
 */
class RetryFailedWebhooksCommand extends Command
{
    protected $signature = 'webhooks:retry-failed {--limit=100}';
    protected $description = 'تلاش مجدد برای webhook deliveries failed/retrying';

    public function handle(WebhookDispatchService $dispatcher): int
    {
        $limit = (int) $this->option('limit');

        $retriable = WebhookDelivery::query()
            ->retriable()
            ->limit($limit)
            ->get();

        if ($retriable->isEmpty()) {
            $this->info('هیچ delivery برای retry نیست.');
            return self::SUCCESS;
        }

        $this->info("تعداد {$retriable->count()} delivery در صف retry.");

        foreach ($retriable as $delivery) {
            try {
                $dispatcher->attempt($delivery);
                $this->info("✓ Delivery #{$delivery->id} retry شد.");
            } catch (\Throwable $e) {
                $this->error("✗ Delivery #{$delivery->id}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
