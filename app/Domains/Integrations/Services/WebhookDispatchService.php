<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Services;

use App\Domains\Integrations\Enums\IntegrationHealthStatus;
use App\Domains\Integrations\Enums\WebhookDeliveryStatus;
use App\Domains\Integrations\Models\ApiWebhook;
use App\Domains\Integrations\Models\WebhookDelivery;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

/**
 * WebhookDispatchService — تولید و dispatch webhook ها.
 *
 * فرایند:
 * 1. dispatch($event, $payload) — همه webhookهای فعال subscriber به این event را پیدا می‌کند
 * 2. برای هر یک، یک WebhookDelivery می‌سازد
 * 3. تلاش اول synchronous؛ در صورت شکست، retry با exponential backoff
 *
 * امضای HMAC:
 *   X-MMS-Signature: sha256=<HMAC-SHA256(secret, payload)>
 *   X-MMS-Event: meeting.created
 *   X-MMS-Delivery-ID: <uuid>
 *   X-MMS-Timestamp: <unix-ts>
 */
class WebhookDispatchService
{
    private const MAX_RETRY_BASE_SECONDS = 30;

    /**
     * dispatch یک event به همه webhook ها — برای event هایی مثل
     * meeting.created, resolution.approved, task.completed
     *
     * @return array<int, WebhookDelivery> deliveries ایجاد شده
     */
    public function dispatch(string $eventType, array $payload, ?int $organizationId = null): array
    {
        $query = ApiWebhook::query()->active()->forEvent($eventType);
        if ($organizationId) {
            $query->where(fn ($q) => $q->whereNull('organization_id')->orWhere('organization_id', $organizationId));
        }

        $webhooks = $query->get();
        $deliveries = [];

        foreach ($webhooks as $webhook) {
            $deliveries[] = $this->dispatchToWebhook($webhook, $eventType, $payload);
        }

        return $deliveries;
    }

    public function dispatchToWebhook(ApiWebhook $webhook, string $eventType, array $payload): WebhookDelivery
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $signature = $this->sign($body, $webhook->secret);

        $delivery = WebhookDelivery::create([
            'webhook_id' => $webhook->id,
            'event_type' => $eventType,
            'payload' => $payload,
            'payload_signature' => $signature,
            'status' => WebhookDeliveryStatus::Pending,
        ]);

        $this->attempt($delivery, $webhook);

        return $delivery->fresh();
    }

    /**
     * تلاش برای ارسال یک delivery — اگر شکست خورد، next_retry_at را ست می‌کند.
     */
    public function attempt(WebhookDelivery $delivery, ?ApiWebhook $webhook = null): void
    {
        $webhook ??= $delivery->webhook;
        $body = json_encode($delivery->payload, JSON_UNESCAPED_UNICODE);
        $signature = $delivery->payload_signature
            ?: $this->sign($body, $webhook->secret);

        $start = microtime(true);
        $delivery->update([
            'attempts' => $delivery->attempts + 1,
            'first_attempted_at' => $delivery->first_attempted_at ?? now(),
            'last_attempted_at' => now(),
        ]);

        try {
            $client = new Client([
                'timeout' => $webhook->timeout_seconds,
                'verify' => $webhook->verify_ssl,
                'http_errors' => false,
            ]);

            $headers = array_merge($webhook->headers ?? [], [
                'Content-Type' => 'application/json',
                'X-MMS-Signature' => 'sha256=' . $signature,
                'X-MMS-Event' => $delivery->event_type,
                'X-MMS-Delivery-ID' => (string) $delivery->id,
                'X-MMS-Timestamp' => (string) time(),
                'User-Agent' => 'MMS-Webhook/1.0',
            ]);

            $response = $client->post($webhook->url, [
                'headers' => $headers,
                'body' => $body,
            ]);

            $duration = (int) ((microtime(true) - $start) * 1000);
            $statusCode = $response->getStatusCode();

            if ($statusCode >= 200 && $statusCode < 300) {
                $delivery->update([
                    'status' => WebhookDeliveryStatus::Success,
                    'http_status' => $statusCode,
                    'response_body' => substr((string) $response->getBody(), 0, 5000),
                    'delivered_at' => now(),
                    'total_duration_ms' => $duration,
                    'error_message' => null,
                    'next_retry_at' => null,
                ]);

                $webhook->forceFill([
                    'last_success_at' => now(),
                    'consecutive_failures' => 0,
                    'health_status' => IntegrationHealthStatus::Healthy,
                ])->save();
            } else {
                $this->handleFailure($delivery, $webhook, $statusCode, (string) $response->getBody(), $duration);
            }
        } catch (GuzzleException | \Throwable $e) {
            $duration = (int) ((microtime(true) - $start) * 1000);
            $this->handleFailure($delivery, $webhook, null, $e->getMessage(), $duration);
        }
    }

    private function handleFailure(
        WebhookDelivery $delivery,
        ApiWebhook $webhook,
        ?int $httpStatus,
        string $errorMessage,
        int $duration,
    ): void {
        $isFinalAttempt = $delivery->attempts >= $webhook->max_retries;

        $nextRetry = $isFinalAttempt
            ? null
            : now()->addSeconds(self::MAX_RETRY_BASE_SECONDS * (2 ** ($delivery->attempts - 1)));

        $delivery->update([
            'status' => $isFinalAttempt ? WebhookDeliveryStatus::Failed : WebhookDeliveryStatus::Retrying,
            'http_status' => $httpStatus,
            'error_message' => substr($errorMessage, 0, 5000),
            'response_body' => substr($errorMessage, 0, 5000),
            'next_retry_at' => $nextRetry,
            'total_duration_ms' => $duration,
        ]);

        $webhook->forceFill([
            'last_failure_at' => now(),
            'consecutive_failures' => $webhook->consecutive_failures + 1,
            'health_status' => $webhook->consecutive_failures >= 5
                ? IntegrationHealthStatus::Down
                : IntegrationHealthStatus::Degraded,
        ])->save();

        Log::warning('Webhook delivery failed', [
            'webhook_id' => $webhook->id,
            'delivery_id' => $delivery->id,
            'attempt' => $delivery->attempts,
            'error' => $errorMessage,
        ]);
    }

    public function sign(string $body, string $secret): string
    {
        return hash_hmac('sha256', $body, $secret);
    }

    public function verifySignature(string $body, string $signature, string $secret): bool
    {
        $expected = $this->sign($body, $secret);
        return hash_equals($expected, $signature);
    }
}
