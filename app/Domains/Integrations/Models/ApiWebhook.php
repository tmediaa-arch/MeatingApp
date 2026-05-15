<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * ApiWebhook — webhook های outbound برای integration با سامانه‌های خارجی.
 *
 * با جدول api_webhooks هماهنگ است (migration فاز ۶).
 */
class ApiWebhook extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'created_by_user_id',
        'name',
        'url',
        'events',
        'secret',
        'verify_ssl',
        'is_active',
        'health_status',
        'last_success_at',
        'last_failure_at',
        'consecutive_failures',
        'max_retries',
        'timeout_seconds',
        'headers',
        'metadata',
    ];

    protected $casts = [
        'events' => 'array',
        'verify_ssl' => 'boolean',
        'is_active' => 'boolean',
        'last_success_at' => 'datetime',
        'last_failure_at' => 'datetime',
        'consecutive_failures' => 'integer',
        'max_retries' => 'integer',
        'timeout_seconds' => 'integer',
        'headers' => 'array',
        'metadata' => 'array',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class, 'webhook_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * webhook هایی که به یک event نوع خاص subscribe کرده‌اند.
     */
    public function scopeForEvent(Builder $query, string $eventType): Builder
    {
        return $query->whereJsonContains('events', $eventType);
    }

    /**
     * تولید secret تصادفی برای امضای HMAC.
     */
    public static function generateSecret(): string
    {
        return 'whsec_' . Str::random(48);
    }
}
