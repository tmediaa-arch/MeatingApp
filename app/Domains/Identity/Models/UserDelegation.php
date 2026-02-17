<?php

declare(strict_types=1);

namespace App\Domains\Identity\Models;

use App\Domains\Shared\Concerns\HasAuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class UserDelegation
 *
 * تفویض اختیار موقت از یک کاربر به کاربر دیگر.
 *
 * نکات منطقی:
 * - یک کاربر می‌تواند چند تفویض موازی داشته باشد (به افراد مختلف، با scope های متفاوت)
 * - تفویض پس از ends_at اعتبار ندارد حتی اگر status تغییر نکرده
 * - revoked یعنی delegator خودش لغو کرده
 * - completed یعنی بازه تمام شده و سیستم آن را close کرده
 */
class UserDelegation extends Model
{
    use HasAuditLog;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'delegator_user_id',
        'delegate_user_id',
        'starts_at',
        'ends_at',
        'scope',
        'restricted_to',
        'reason',
        'reason_description',
        'status',
        'decree_number',
        'decree_date',
        'created_by',
        'revoked_by',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'decree_date' => 'date',
            'revoked_at' => 'datetime',
            'restricted_to' => 'array',
            'actions_count' => 'integer',
            'last_used_at' => 'datetime',
        ];
    }

    // ------------------------- Relationships ------------------------- //

    public function delegator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegator_user_id');
    }

    public function delegate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegate_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    // ------------------------- Scopes ------------------------- //

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now());
    }

    public function scopeForDelegate(Builder $query, int $userId): Builder
    {
        return $query->where('delegate_user_id', $userId);
    }

    public function scopeWithScope(Builder $query, string $scope): Builder
    {
        return $query->whereIn('scope', ['all', $scope]);
    }

    // ------------------------- Business Logic ------------------------- //

    public function isActive(): bool
    {
        return $this->status === 'active'
            && $this->starts_at?->isPast()
            && $this->ends_at?->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->ends_at?->isPast() ?? false;
    }

    /**
     * آیا این تفویض scope مشخص را پوشش می‌دهد؟
     */
    public function coversScope(string $scope): bool
    {
        return $this->scope === 'all' || $this->scope === $scope;
    }

    /**
     * آیا این تفویض موجودیت مشخصی را پوشش می‌دهد؟
     * (اگر restricted_to مقدار داشته باشد، باید id در آن وجود داشته باشد)
     */
    public function coversEntity(string $entityType, int $entityId): bool
    {
        if (empty($this->restricted_to)) {
            return true; // محدودیتی نیست — همه را پوشش می‌دهد
        }

        $allowedIds = $this->restricted_to[$entityType] ?? null;

        // اگر این entityType اصلاً در restricted_to نیست، یعنی پوشش داده نمی‌شود
        return is_array($allowedIds) && in_array($entityId, $allowedIds, true);
    }
}
