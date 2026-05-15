<?php

declare(strict_types=1);

namespace App\Domains\Exports\Models;

use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CalendarFeedToken — token برای دسترسی عمومی به ICS feed تقویم کاربر.
 */
class CalendarFeedToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'token',
        'name',
        'filter_config',
        'is_active',
        'expires_at',
        'last_accessed_at',
        'access_count',
    ];

    protected $casts = [
        'filter_config' => 'array',
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
        'last_accessed_at' => 'datetime',
        'access_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    /**
     * ثبت یک دسترسی موفق به feed.
     */
    public function recordAccess(): void
    {
        $this->forceFill([
            'last_accessed_at' => now(),
            'access_count' => $this->access_count + 1,
        ])->save();
    }
}
