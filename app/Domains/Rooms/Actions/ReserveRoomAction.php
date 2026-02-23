<?php

declare(strict_types=1);

namespace App\Domains\Rooms\Actions;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Calendar\ValueObjects\TimeRange;
use App\Domains\Meetings\Exceptions\MeetingException;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Rooms\Enums\ReservationStatus;
use App\Domains\Rooms\Models\Room;
use App\Domains\Rooms\Models\RoomReservation;
use App\Domains\Rooms\Services\RoomConflictDetectionService;
use Illuminate\Support\Facades\DB;

/**
 * هسته رزرو سالن.
 *
 * مسئولیت‌ها:
 * 1. Lock کردن row سالن برای جلوگیری از race condition
 * 2. اعتبارسنجی با ConflictDetectionService
 * 3. تعیین وضعیت اولیه: Approved در reservation_policy=free، Pending در approval
 * 4. ایجاد RoomReservation با buffer
 * 5. audit log
 *
 * مهم: این Action atomic است — قفل row می‌کند تا two-phase commit موثر باشد.
 */
class ReserveRoomAction
{
    public function __construct(
        private readonly RoomConflictDetectionService $conflictService,
        private readonly AuditService $auditService,
    ) {
    }

    /**
     * @param array $specialRequirements پیکربندی خاص (چیدمان، تجهیزات اضافه)
     */
    public function execute(
        Room $room,
        TimeRange $range,
        string $purpose,
        ?Meeting $meeting = null,
        ?int $expectedAttendees = null,
        array $specialRequirements = [],
        string $reservationType = 'meeting',
        ?int $requestedByUserId = null,
    ): RoomReservation {
        return DB::transaction(function () use (
            $room, $range, $purpose, $meeting, $expectedAttendees,
            $specialRequirements, $reservationType, $requestedByUserId,
        ) {
            // 1. lock سالن برای جلوگیری از race
            $lockedRoom = Room::query()->lockForUpdate()->findOrFail($room->id);

            // 2. اعتبارسنجی
            $validation = $this->conflictService->validateReservation($lockedRoom, $range);

            if (!$validation['valid']) {
                throw MeetingException::roomNotAvailable(
                    $lockedRoom->name . ' — ' . implode(' | ', $validation['errors'])
                );
            }

            // 3. ظرفیت
            if ($expectedAttendees && $expectedAttendees > $lockedRoom->capacity) {
                $maxCapacity = $lockedRoom->max_capacity ?? $lockedRoom->capacity;
                if ($expectedAttendees > $maxCapacity) {
                    throw MeetingException::roomCapacityExceeded($maxCapacity, $expectedAttendees);
                }
            }

            // 4. تعیین وضعیت اولیه
            $initialStatus = match ($lockedRoom->reservation_policy) {
                'free' => ReservationStatus::Approved,
                default => ReservationStatus::Pending,
            };

            // 5. ایجاد reservation با احتساب buffer
            $bufferedRange = $range->expand(
                $lockedRoom->buffer_before_minutes,
                $lockedRoom->buffer_after_minutes,
            );

            $reservation = RoomReservation::create([
                'room_id' => $lockedRoom->id,
                'meeting_id' => $meeting?->id,
                'reservation_type' => $reservationType,
                'reserved_from' => $bufferedRange->start,
                'reserved_until' => $bufferedRange->end,
                'effective_from' => $range->start,
                'effective_until' => $range->end,
                'requested_by_user_id' => $requestedByUserId ?? auth()->id(),
                'purpose' => $purpose,
                'expected_attendees' => $expectedAttendees,
                'status' => $initialStatus,
                'approved_by_user_id' => $initialStatus === ReservationStatus::Approved
                    ? ($requestedByUserId ?? auth()->id())
                    : null,
                'approved_at' => $initialStatus === ReservationStatus::Approved ? now() : null,
                'special_requirements' => empty($specialRequirements) ? null : $specialRequirements,
            ]);

            // 6. audit
            $this->auditService->log(
                event: 'room_reserved',
                auditable: $reservation,
                description: sprintf(
                    "سالن '%s' برای '%s' از %s تا %s رزرو شد (وضعیت: %s)",
                    $lockedRoom->name,
                    $purpose,
                    $range->start->format('Y/m/d H:i'),
                    $range->end->format('Y/m/d H:i'),
                    $initialStatus->label(),
                ),
                context: [
                    'room_id' => $lockedRoom->id,
                    'meeting_id' => $meeting?->id,
                    'duration_minutes' => $range->durationInMinutes(),
                    'expected_attendees' => $expectedAttendees,
                    'initial_status' => $initialStatus->value,
                ],
                severity: 'info',
            );

            return $reservation;
        });
    }
}
