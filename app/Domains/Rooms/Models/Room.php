<?php

declare(strict_types=1);

namespace App\Domains\Rooms\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Organization\Models\OrgUnit;
use App\Domains\Organization\Models\Organization;
use App\Domains\Rooms\Enums\RoomStatus;
use App\Domains\Shared\Concerns\HasAuditLog;
use App\Domains\Shared\Concerns\TracksUserChanges;
use App\Domains\Shared\Enums\ConfidentialityLevel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Room extends Model
{
    use HasFactory;
    use SoftDeletes;
    use HasAuditLog;
    use TracksUserChanges;

    protected $fillable = [
        'organization_id',
        'owner_org_unit_id',
        'code',
        'name',
        'english_name',
        'description',
        'capacity',
        'max_capacity',
        'layout_type',
        'building',
        'floor',
        'room_number',
        'directions',
        'latitude',
        'longitude',
        'equipment',
        'has_projector',
        'has_video_conference',
        'has_whiteboard',
        'has_audio_system',
        'has_recording',
        'has_wifi',
        'has_accessibility',
        'reservation_policy',
        'min_booking_minutes',
        'max_booking_minutes',
        'buffer_before_minutes',
        'buffer_after_minutes',
        'advance_booking_days',
        'working_hours',
        'status',
        'activated_at',
        'decommissioned_at',
        'confidentiality_level',
        'photos',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'max_capacity' => 'integer',
        'min_booking_minutes' => 'integer',
        'max_booking_minutes' => 'integer',
        'buffer_before_minutes' => 'integer',
        'buffer_after_minutes' => 'integer',
        'advance_booking_days' => 'integer',
        'has_projector' => 'boolean',
        'has_video_conference' => 'boolean',
        'has_whiteboard' => 'boolean',
        'has_audio_system' => 'boolean',
        'has_recording' => 'boolean',
        'has_wifi' => 'boolean',
        'has_accessibility' => 'boolean',
        'equipment' => 'array',
        'working_hours' => 'array',
        'photos' => 'array',
        'metadata' => 'array',
        'activated_at' => 'date',
        'decommissioned_at' => 'date',
        'status' => RoomStatus::class,
        'confidentiality_level' => ConfidentialityLevel::class,
    ];

    // ----------------------------- Relations ----------------------------- //

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function ownerOrgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'owner_org_unit_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(RoomReservation::class);
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class);
    }

    public function managers()
    {
        return $this->belongsToMany(User::class, 'room_managers')
            ->withPivot(['role', 'receive_notifications'])
            ->withTimestamps();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ----------------------------- Scopes ----------------------------- //

    public function scopeActive($query)
    {
        return $query->where('status', RoomStatus::Active);
    }

    public function scopeBookable($query)
    {
        return $query->where('status', RoomStatus::Active);
    }

    public function scopeWithCapacityAtLeast($query, int $capacity)
    {
        return $query->where('capacity', '>=', $capacity);
    }

    public function scopeWithEquipment($query, string $equipment)
    {
        $flagColumn = "has_{$equipment}";
        if (in_array($flagColumn, [
            'has_projector', 'has_video_conference', 'has_whiteboard',
            'has_audio_system', 'has_recording', 'has_wifi', 'has_accessibility',
        ], true)) {
            return $query->where($flagColumn, true);
        }
        return $query;
    }

    // ----------------------------- Helpers ----------------------------- //

    /**
     * بررسی اینکه آیا کاربر می‌تواند این سالن را رزرو کند
     * (با توجه به سیاست reservation_policy و سطح محرمانگی)
     */
    public function isReservableBy(User $user): bool
    {
        if (!$this->status->isBookable()) {
            return false;
        }

        // بررسی محرمانگی
        if (!$user->clearanceLevel()->canAccess($this->confidentiality_level)) {
            return false;
        }

        if ($this->reservation_policy === 'free' || $this->reservation_policy === 'approval') {
            return true;
        }

        // restricted — باید در whitelist باشد
        return $this->isAllowedPrincipal($user);
    }

    public function isAllowedPrincipal(User $user): bool
    {
        return \DB::table('room_allowed_principals')
            ->where('room_id', $this->id)
            ->where(function ($q) use ($user) {
                $q->where(function ($q1) use ($user) {
                    $q1->where('principal_type', 'user')->where('principal_id', $user->id);
                });
                if ($user->employee) {
                    $q->orWhere(function ($q1) use ($user) {
                        $q1->where('principal_type', 'org_unit')
                            ->where('principal_id', $user->employee->current_org_unit_id);
                    });
                }
                foreach ($user->roles as $role) {
                    $q->orWhere(function ($q1) use ($role) {
                        $q1->where('principal_type', 'role')->where('principal_id', $role->id);
                    });
                }
            })
            ->exists();
    }

    /**
     * آیا این سالن در زمان مشخص قابل رزرو است؟
     * (بدون چک تداخل — فقط ساعت کاری و دسترسی روزانه)
     */
    public function isInWorkingHours(\DateTimeInterface $from, \DateTimeInterface $until): bool
    {
        $hours = $this->working_hours;
        if (empty($hours)) {
            return true; // بدون محدودیت
        }

        $dayKey = strtolower((new \DateTimeImmutable($from->format('c')))->format('D'));
        $dayHours = $hours[$dayKey] ?? null;

        if (!$dayHours) {
            return false; // روز کاری نیست
        }

        $start = $dayHours['start'] ?? '00:00';
        $end = $dayHours['end'] ?? '23:59';

        $fromTime = $from->format('H:i');
        $untilTime = $until->format('H:i');

        return $fromTime >= $start && $untilTime <= $end;
    }

    public function getFullLocationAttribute(): string
    {
        $parts = array_filter([
            $this->building,
            $this->floor ? "طبقه {$this->floor}" : null,
            $this->room_number ? "اتاق {$this->room_number}" : null,
        ]);
        return implode('، ', $parts);
    }

    /**
     * آیا واحد owner داخل subtree واحد کاربر هست یا برعکس؟
     * (برای reservation policy های مبتنی بر سازمان)
     */
    public function isOwnedByOrUnder(OrgUnit $unit): bool
    {
        if (!$this->owner_org_unit_id) {
            return false;
        }

        return $this->owner_org_unit_id === $unit->id
            || $this->ownerOrgUnit?->isDescendantOf($unit);
    }
}
