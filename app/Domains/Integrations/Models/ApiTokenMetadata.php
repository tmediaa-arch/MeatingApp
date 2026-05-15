<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Models;

use App\Domains\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ApiTokenMetadata — متادیتای امنیتی برای personal access token های Sanctum
 * (rate limit، IP whitelist، انقضا/ابطال).
 */
class ApiTokenMetadata extends Model
{
    use HasFactory;

    protected $table = 'api_token_metadata';

    protected $fillable = [
        'token_id',
        'organization_id',
        'description',
        'rate_limit_per_minute',
        'rate_limit_per_day',
        'allowed_ips',
        'expires_at',
        'revoked_at',
        'revoked_reason',
        'total_requests',
        'last_used_at',
        'last_used_ip',
        'metadata',
    ];

    protected $casts = [
        'rate_limit_per_minute' => 'integer',
        'rate_limit_per_day' => 'integer',
        'allowed_ips' => 'array',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'total_requests' => 'integer',
        'last_used_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * token هنوز معتبر است (نه باطل، نه منقضی).
     */
    public function isActive(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    /**
     * آیا IP داده‌شده در whitelist است؟ whitelist خالی یعنی همه مجاز.
     */
    public function isIpAllowed(string $ip): bool
    {
        $allowed = $this->allowed_ips ?? [];
        if (empty($allowed)) {
            return true;
        }

        return in_array($ip, $allowed, true);
    }
}
