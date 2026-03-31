<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Jobs;

use App\Domains\Integrations\Models\WebhookDelivery;
use App\Domains\Integrations\Services\WebhookDispatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * RetryFailedWebhooksJob — تلاش مجدد برای delivery هایی که next_retry_at آن‌ها رسیده.
 *
 * توسط دستور scheduled `webhooks:retry-failed` (every 5 min) فراخوانی می‌شود.
 */
class RetryFailedWebhooksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 600;

    public function handle(WebhookDispatchService $service): void
    {
        $retriable = WebhookDelivery::query()
            ->retriable()
            ->with('webhook')
            ->limit(100)
            ->get();

        foreach ($retriable as $delivery) {
            if (!$delivery->webhook || !$delivery->webhook->is_active) {
                continue;
            }
            $service->attempt($delivery, $delivery->webhook);
        }
    }
}
