<?php

declare(strict_types=1);

namespace App\Domains\Identity\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * کد یک‌بارمصرف اعتبارسنجی (OTP).
 */
class OtpCode extends Model
{
    protected $fillable = [
        'mobile', 'code_hash', 'purpose', 'user_id',
        'attempts', 'ip_address', 'expires_at', 'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isUsable(): bool
    {
        return $this->consumed_at === null
            && $this->expires_at->isFuture();
    }

    public function scopeUsable(Builder $query): Builder
    {
        return $query->whereNull('consumed_at')
            ->where('expires_at', '>', now());
    }

    public function scopeForLogin(Builder $query, string $mobile, string $purpose): Builder
    {
        return $query->where('mobile', $mobile)->where('purpose', $purpose);
    }
}
