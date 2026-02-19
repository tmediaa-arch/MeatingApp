<?php

declare(strict_types=1);

namespace App\Domains\Audit\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\UserDelegation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Class AuditLog
 *
 * این مدل به‌شدت append-only است:
 * - هیچ UPDATE یا DELETE روی آن مجاز نیست (در سطح Model abort می‌کند)
 * - timestamps فقط performed_at است (created_at/updated_at حذف شده)
 * - برای ممیزی سازمانی این رفتار ضروری است
 *
 * در سطح دیتابیس هم باید REVOKE UPDATE,DELETE روی این جدول از همه نقش‌ها
 * انجام شود (وظیفه DBA).
 */
class AuditLog extends Model
{
    use HasFactory;

    public $timestamps = false;
    protected $table = 'audit_logs';

    protected $fillable = [
        'user_id', 'user_display_name',
        'on_behalf_of_user_id', 'delegation_id',
        'auditable_type', 'auditable_id',
        'event', 'action_category',
        'old_values', 'new_values', 'changed_fields',
        'description', 'context',
        'ip_address', 'user_agent', 'request_method', 'request_url',
        'request_id', 'tag', 'correlation_id',
        'severity',
        'performed_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'changed_fields' => 'array',
            'context' => 'array',
            'performed_at' => 'datetime',
        ];
    }

    /**
     * Append-only enforcement
     */
    protected static function booted(): void
    {
        static::updating(function () {
            throw new \LogicException(
                'AuditLog is append-only. Updates are forbidden.'
            );
        });

        static::deleting(function () {
            throw new \LogicException(
                'AuditLog is append-only. Deletions are forbidden.'
            );
        });
    }

    // ------------------------- Relationships ------------------------- //

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function onBehalfOf(): BelongsTo
    {
        return $this->belongsTo(User::class, 'on_behalf_of_user_id');
    }

    public function delegation(): BelongsTo
    {
        return $this->belongsTo(UserDelegation::class, 'delegation_id');
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    // ------------------------- Scopes ------------------------- //

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForModel(Builder $query, Model $model): Builder
    {
        return $query
            ->where('auditable_type', $model->getMorphClass())
            ->where('auditable_id', $model->getKey());
    }

    public function scopeOfEvent(Builder $query, string $event): Builder
    {
        return $query->where('event', $event);
    }

    public function scopeWithCorrelation(Builder $query, string $correlationId): Builder
    {
        return $query->where('correlation_id', $correlationId);
    }

    public function scopeInDateRange(Builder $query, \DateTimeInterface $from, \DateTimeInterface $to): Builder
    {
        return $query->whereBetween('performed_at', [$from, $to]);
    }

    public function scopeSeverity(Builder $query, string|array $severity): Builder
    {
        $severities = (array) $severity;
        return $query->whereIn('severity', $severities);
    }
}
