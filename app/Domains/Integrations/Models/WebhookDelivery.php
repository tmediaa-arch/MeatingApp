<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Models;

use App\Domains\Integrations\Enums\WebhookDeliveryStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * WebhookDelivery — APPEND-ONLY ردگیری تحویل webhook ها.
 *
 * فقط فیلدهای attempt مجاز به تغییر هستند (برای retry). حذف ممنوع است.
 */
class WebhookDelivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'webhook_id', 'event_type', 'payload', 'payload_signature',
        'status', 'attempts', 'http_status', 'response_body', 'error_message',
        'first_attempted_at', 'last_attempted_at', 'next_retry_at', 'delivered_at',
        'total_duration_ms',
    ];

    protected $casts = [
        'status' => WebhookDeliveryStatus::class,
        'payload' => 'array',
        'first_attempted_at' => 'datetime',
        'last_attempted_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    private const ALLOWED_UPDATE_FIELDS = [
        'status', 'attempts', 'http_status', 'response_body', 'error_message',
        'first_attempted_at', 'last_attempted_at', 'next_retry_at', 'delivered_at',
        'total_duration_ms',
    ];

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(ApiWebhook::class, 'webhook_id');
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', [
            WebhookDeliveryStatus::Pending,
            WebhookDeliveryStatus::Retrying,
        ]);
    }

    public function scopeRetriable($query)
    {
        return $query
            ->where('status', WebhookDeliveryStatus::Retrying)
            ->whereNotNull('next_retry_at')
            ->where('next_retry_at', '<=', now());
    }

    public function update(array $attributes = [], array $options = [])
    {
        $disallowed = array_diff(array_keys($attributes), self::ALLOWED_UPDATE_FIELDS);
        if (!empty($disallowed)) {
            throw new \LogicException(
                'فیلدهای ' . implode(', ', $disallowed) . ' در WebhookDelivery قابل تغییر نیستند.'
            );
        }
        return parent::update($attributes, $options);
    }

    public function delete()
    {
        throw new \LogicException('WebhookDelivery قابل حذف نیست (append-only).');
    }
}
