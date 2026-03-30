<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Models;

use App\Domains\Audit\Concerns\HasAuditLog;
use App\Domains\Integrations\Contracts\IntegrationDriverInterface;
use App\Domains\Integrations\Enums\IntegrationHealthStatus;
use App\Domains\Integrations\Enums\IntegrationType;
use App\Domains\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class IntegrationProvider extends Model
{
    use HasFactory, HasAuditLog, SoftDeletes;

    protected $fillable = [
        'organization_id', 'key', 'display_name',
        'type', 'driver', 'config',
        'is_active', 'health_status', 'last_health_check_at', 'last_health_message',
        'auto_sync_enabled', 'sync_schedule', 'last_sync_at', 'next_sync_at',
        'total_syncs', 'successful_syncs',
        'metadata',
    ];

    protected $casts = [
        'type' => IntegrationType::class,
        'health_status' => IntegrationHealthStatus::class,
        'config' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
        'auto_sync_enabled' => 'boolean',
        'last_health_check_at' => 'datetime',
        'last_sync_at' => 'datetime',
        'next_sync_at' => 'datetime',
    ];

    // ──────── روابط ────────

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(IntegrationSyncLog::class, 'provider_id');
    }

    public function ldapMappings(): HasMany
    {
        return $this->hasMany(LdapUserMapping::class, 'provider_id');
    }

    public function ssoSessions(): HasMany
    {
        return $this->hasMany(SsoSession::class, 'provider_id');
    }

    // ──────── Scopes ────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, IntegrationType|string $type)
    {
        $value = $type instanceof IntegrationType ? $type->value : $type;
        return $query->where('type', $value);
    }

    public function scopeDue($query)
    {
        return $query
            ->where('is_active', true)
            ->where('auto_sync_enabled', true)
            ->whereNotNull('next_sync_at')
            ->where('next_sync_at', '<=', now());
    }

    // ──────── Helpers ────────

    public function makeDriver(): IntegrationDriverInterface
    {
        $driverClass = config("integrations.drivers.{$this->type->value}.{$this->driver}");

        if (!$driverClass || !class_exists($driverClass)) {
            throw new \LogicException("Driver '{$this->driver}' for type '{$this->type->value}' یافت نشد.");
        }

        $driver = app($driverClass, ['provider' => $this]);

        if (!$driver instanceof IntegrationDriverInterface) {
            throw new \LogicException("Driver '{$driverClass}' باید IntegrationDriverInterface را پیاده‌سازی کند.");
        }

        return $driver;
    }

    /**
     * یک کلید config را بازمی‌گرداند (با dot notation)
     */
    public function getConfigValue(string $key, mixed $default = null): mixed
    {
        return data_get($this->config, $key, $default);
    }
}
