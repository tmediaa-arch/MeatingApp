<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Enums\NotificationChannel;
use App\Domains\Notifications\Enums\NotificationStatus;
use App\Domains\Organization\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * یک اعلان در صف ارسال یا ارسال شده.
 *
 * این جدول دو نقش دارد:
 * 1. Outbox برای ارسال
 * 2. Inbox برای نمایش (در صورت channel=in_app)
 */
class NotificationOutbox extends Model
{
    use HasFactory;

    protected $table = 'notifications_outbox';

    protected $fillable = [
        'correlation_id', 'template_id',
        'recipient_user_id', 'recipient_employee_id',
        'channel', 'to_address', 'subject', 'body', 'body_html',
        'notifiable_type', 'notifiable_id',
        'status', 'priority',
        'scheduled_at', 'sent_at', 'delivered_at',
        'opened_at', 'clicked_at',
        'attempts', 'max_attempts', 'last_error', 'next_retry_at',
        'read_in_inbox', 'read_at', 'archived_in_inbox',
        'provider_response', 'metadata',
    ];

    protected $casts = [
        'channel' => NotificationChannel::class,
        'status' => NotificationStatus::class,
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'opened_at' => 'datetime',
        'clicked_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'read_at' => 'datetime',
        'read_in_inbox' => 'boolean',
        'archived_in_inbox' => 'boolean',
        'provider_response' => 'array',
        'metadata' => 'array',
    ];

    // ──────── روابط ────────

    public function template(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class, 'template_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function recipientEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'recipient_employee_id');
    }

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    // ──────── Scopes ────────

    public function scopeDue(Builder $query): Builder
    {
        return $query
            ->where('status', NotificationStatus::Pending->value)
            ->where(function ($q) {
                $q->whereNull('scheduled_at')
                  ->orWhere('scheduled_at', '<=', now());
            });
    }

    public function scopeForInbox(Builder $query, User $user): Builder
    {
        return $query
            ->where('recipient_user_id', $user->id)
            ->where('channel', NotificationChannel::InApp->value)
            ->where('archived_in_inbox', false);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('read_in_inbox', false);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', NotificationStatus::Failed->value);
    }

    public function scopeRetryable(Builder $query): Builder
    {
        return $query
            ->where('status', NotificationStatus::Failed->value)
            ->whereColumn('attempts', '<', 'max_attempts')
            ->where(function ($q) {
                $q->whereNull('next_retry_at')
                  ->orWhere('next_retry_at', '<=', now());
            });
    }

    // ──────── Helpers ────────

    public function markAsSent(?array $providerResponse = null): void
    {
        $this->update([
            'status' => NotificationStatus::Sent,
            'sent_at' => now(),
            'attempts' => $this->attempts + 1,
            'provider_response' => $providerResponse,
        ]);
    }

    public function markAsDelivered(): void
    {
        $this->update([
            'status' => NotificationStatus::Delivered,
            'delivered_at' => now(),
        ]);
    }

    public function markAsFailed(string $error): void
    {
        $shouldRetry = $this->attempts + 1 < $this->max_attempts;

        $this->update([
            'status' => NotificationStatus::Failed,
            'attempts' => $this->attempts + 1,
            'last_error' => $error,
            'next_retry_at' => $shouldRetry
                ? now()->addMinutes($this->getRetryDelay())
                : null,
        ]);
    }

    public function markAsRead(): void
    {
        if ($this->read_in_inbox) return;
        $this->update([
            'read_in_inbox' => true,
            'read_at' => now(),
            'opened_at' => $this->opened_at ?? now(),
            'status' => NotificationStatus::Opened,
        ]);
    }

    public function archive(): void
    {
        $this->update(['archived_in_inbox' => true]);
    }

    /**
     * Exponential backoff
     */
    private function getRetryDelay(): int
    {
        return match ($this->attempts) {
            0 => 1,
            1 => 5,
            2 => 30,
            3 => 120,
            default => 360,
        };
    }
}
