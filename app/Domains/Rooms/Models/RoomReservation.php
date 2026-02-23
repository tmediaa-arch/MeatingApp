<?php

declare(strict_types=1);

namespace App\Domains\Rooms\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Rooms\Enums\ReservationStatus;
use App\Domains\Shared\Concerns\HasAuditLog;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RoomReservation extends Model
{
    use HasFactory;
    use SoftDeletes;
    use HasAuditLog;

    protected $fillable = [
        'room_id',
        'meeting_id',
        'reservation_type',
        'reserved_from',
        'reserved_until',
        'effective_from',
        'effective_until',
        'requested_by_user_id',
        'purpose',
        'expected_attendees',
        'status',
        'approved_by_user_id',
        'approved_at',
        'rejected_by_user_id',
        'rejected_at',
        'rejection_reason',
        'is_override',
        'overridden_by_user_id',
        'override_reason',
        'special_requirements',
        'metadata',
    ];

    protected $casts = [
        'reserved_from' => 'datetime',
        'reserved_until' => 'datetime',
        'effective_from' => 'datetime',
        'effective_until' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'expected_attendees' => 'integer',
        'is_override' => 'boolean',
        'special_requirements' => 'array',
        'metadata' => 'array',
        'status' => ReservationStatus::class,
    ];

    // ----------------------------- Relations ----------------------------- //

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by_user_id');
    }

    // ----------------------------- Scopes ----------------------------- //

    public function scopeActive($query)
    {
        return $query->whereIn('status', [
            ReservationStatus::Pending,
            ReservationStatus::Approved,
        ]);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', ReservationStatus::Approved);
    }

    public function scopePending($query)
    {
        return $query->where('status', ReservationStatus::Pending);
    }

    /**
     * scope تداخل: رزرو هایی که با بازه زمانی مشخص تلاقی دارند
     */
    public function scopeOverlapping($query, \DateTimeInterface $from, \DateTimeInterface $until)
    {
        return $query->where(function ($q) use ($from, $until) {
            // یک رزرو با بازه [a, b] با [from, until] تلاقی دارد اگر:
            //   a < until AND b > from
            $q->where('reserved_from', '<', $until)
              ->where('reserved_until', '>', $from);
        });
    }

    // ----------------------------- Helpers ----------------------------- //

    public function getDurationMinutesAttribute(): int
    {
        return CarbonImmutable::parse($this->reserved_from)
            ->diffInMinutes($this->reserved_until);
    }

    public function getEffectiveDurationMinutesAttribute(): int
    {
        if (!$this->effective_from || !$this->effective_until) {
            return $this->duration_minutes;
        }
        return CarbonImmutable::parse($this->effective_from)
            ->diffInMinutes($this->effective_until);
    }

    public function canBeCancelledBy(User $user): bool
    {
        if (!in_array($this->status, [ReservationStatus::Pending, ReservationStatus::Approved], true)) {
            return false;
        }

        // درخواست‌دهنده می‌تواند رزرو خودش را لغو کند
        if ($this->requested_by_user_id === $user->id) {
            return true;
        }

        // مدیران سالن
        return $this->room->managers->contains($user);
    }

    public function canBeApprovedBy(User $user): bool
    {
        if ($this->status !== ReservationStatus::Pending) {
            return false;
        }
        return $this->room->managers
            ->where('pivot.role', '!=', 'viewer')
            ->contains($user);
    }
}
