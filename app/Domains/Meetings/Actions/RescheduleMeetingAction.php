<?php

declare(strict_types=1);

namespace App\Domains\Meetings\Actions;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Calendar\ValueObjects\TimeRange;
use App\Domains\Meetings\Events\MeetingRescheduled;
use App\Domains\Meetings\Exceptions\MeetingException;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Rooms\Actions\CancelReservationAction;
use App\Domains\Rooms\Actions\ReserveRoomAction;
use App\Domains\Rooms\Models\Room;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * تغییر زمان جلسه.
 *
 * منطق:
 * 1. اعتبارسنجی (وضعیت قابل ویرایش، زمان منطقی)
 * 2. لغو رزرو قبلی سالن (در صورت وجود)
 * 3. رزرو سالن جدید با زمان جدید
 * 4. به‌روزرسانی زمان جلسه
 * 5. event MeetingRescheduled برای اطلاع‌رسانی به مدعوین (در فاز ۳)
 */
class RescheduleMeetingAction
{
    public function __construct(
        private readonly CancelReservationAction $cancelReservationAction,
        private readonly ReserveRoomAction $reserveRoomAction,
        private readonly AuditService $auditService,
    ) {
    }

    public function execute(
        Meeting $meeting,
        CarbonImmutable $newStart,
        CarbonImmutable $newEnd,
        ?string $reason = null,
    ): Meeting {
        if (!$meeting->status->isEditable()
            && !in_array($meeting->status, [\App\Domains\Meetings\Enums\MeetingStatus::InvitationsSent], true)
        ) {
            throw MeetingException::cannotEditInStatus($meeting->status);
        }

        if ($newEnd <= $newStart) {
            throw MeetingException::invalidScheduleRange();
        }

        if ($newStart < now()) {
            throw MeetingException::scheduleInPast();
        }

        return DB::transaction(function () use ($meeting, $newStart, $newEnd, $reason) {
            $previousStart = CarbonImmutable::instance($meeting->scheduled_start_at);
            $previousEnd = CarbonImmutable::instance($meeting->scheduled_end_at);

            // اگر سالن داشت، لغو و رزرو مجدد
            if ($meeting->reservation && $meeting->room_id) {
                $this->cancelReservationAction->execute(
                    reservation: $meeting->reservation,
                    reason: 'تغییر زمان جلسه',
                );

                $room = Room::findOrFail($meeting->room_id);
                $this->reserveRoomAction->execute(
                    room: $room,
                    range: TimeRange::from($newStart, $newEnd),
                    purpose: $meeting->subject,
                    meeting: $meeting,
                    expectedAttendees: $meeting->participants()->count() + 2,
                );
            }

            // به‌روزرسانی زمان
            $meeting->update([
                'scheduled_start_at' => $newStart,
                'scheduled_end_at' => $newEnd,
            ]);

            $this->auditService->log(
                event: 'meeting_rescheduled',
                auditable: $meeting,
                description: sprintf(
                    "جلسه '%s' از '%s' به '%s' منتقل شد",
                    $meeting->meeting_number,
                    $previousStart->format('Y/m/d H:i'),
                    $newStart->format('Y/m/d H:i'),
                ),
                oldValues: [
                    'scheduled_start_at' => $previousStart->toIso8601String(),
                    'scheduled_end_at' => $previousEnd->toIso8601String(),
                ],
                newValues: [
                    'scheduled_start_at' => $newStart->toIso8601String(),
                    'scheduled_end_at' => $newEnd->toIso8601String(),
                ],
                context: ['reason' => $reason],
                severity: 'notice',
            );

            event(new MeetingRescheduled(
                meeting: $meeting->fresh(),
                previousStart: $previousStart,
                previousEnd: $previousEnd,
                newStart: $newStart,
                newEnd: $newEnd,
                reason: $reason,
            ));

            return $meeting->fresh();
        });
    }
}
