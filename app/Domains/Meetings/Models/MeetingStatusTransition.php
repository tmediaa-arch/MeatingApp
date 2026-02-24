<?php

declare(strict_types=1);

namespace App\Domains\Meetings\Models;

use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * تاریخچه transition وضعیت جلسه — append-only
 *
 * هیچ‌گاه update یا delete نمی‌شود — مشابه AuditLog.
 */
class MeetingStatusTransition extends Model
{
    use HasFactory;

    protected $fillable = [
        'meeting_id',
        'from_status',
        'to_status',
        'reason',
        'triggered_by_user_id',
        'on_behalf_of_user_id',
        'triggered_via',
        'snapshot',
        'occurred_at',
    ];

    protected $casts = [
        'snapshot' => 'array',
        'occurred_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \LogicException(
                'MeetingStatusTransition records are append-only and cannot be updated.'
            );
        });

        static::deleting(function () {
            throw new \LogicException(
                'MeetingStatusTransition records are append-only and cannot be deleted.'
            );
        });
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }

    public function onBehalfOf(): BelongsTo
    {
        return $this->belongsTo(User::class, 'on_behalf_of_user_id');
    }
}
