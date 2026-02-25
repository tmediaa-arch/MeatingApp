<?php

declare(strict_types=1);

namespace App\Domains\Meetings\Actions;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Meetings\Enums\MeetingStatus;
use App\Domains\Meetings\Events\MeetingStatusChanged;
use App\Domains\Meetings\Exceptions\MeetingException;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Models\MeetingStatusTransition;
use Illuminate\Support\Facades\DB;

/**
 * هسته state machine جلسه.
 *
 * هر تغییر وضعیت جلسه باید از این Action عبور کند (مگر در ایجاد اولیه).
 *
 * مسئولیت:
 * 1. اعتبارسنجی transition با enum
 * 2. به‌روزرسانی meeting.status
 * 3. ثبت در meeting_status_transitions (append-only)
 * 4. در صورت transition به InProgress، actual_start_at را ست می‌کند
 * 5. در صورت transition به Completed، actual_end_at را ست می‌کند
 * 6. در صورت transition به Cancelled، فیلدهای cancellation را ست می‌کند
 * 7. ثبت در audit log
 * 8. پخش رخداد MeetingStatusChanged
 */
class TransitionMeetingStatusAction
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {
    }

    public function execute(
        Meeting $meeting,
        MeetingStatus $newStatus,
        ?string $reason = null,
        string $triggeredVia = 'manual',
    ): Meeting {
        $currentStatus = $meeting->status;

        // idempotent
        if ($currentStatus === $newStatus) {
            return $meeting;
        }

        // اعتبارسنجی transition
        if (!$currentStatus->canTransitionTo($newStatus)) {
            throw MeetingException::invalidStateTransition($currentStatus, $newStatus);
        }

        return DB::transaction(function () use ($meeting, $currentStatus, $newStatus, $reason, $triggeredVia) {
            // snapshot قبل از تغییر
            $snapshot = [
                'scheduled_start_at' => $meeting->scheduled_start_at?->toIso8601String(),
                'scheduled_end_at' => $meeting->scheduled_end_at?->toIso8601String(),
                'room_id' => $meeting->room_id,
                'chairperson_employee_id' => $meeting->chairperson_employee_id,
                'participants_count' => $meeting->participants()->count(),
            ];

            // به‌روزرسانی فیلدهای مرتبط با وضعیت
            $updates = ['status' => $newStatus];

            if ($newStatus === MeetingStatus::InProgress && !$meeting->actual_start_at) {
                $updates['actual_start_at'] = now();
            }

            if ($newStatus === MeetingStatus::Completed && !$meeting->actual_end_at) {
                $updates['actual_end_at'] = now();
            }

            if ($newStatus === MeetingStatus::Cancelled) {
                $updates['cancellation_reason'] = $reason;
                $updates['cancelled_by'] = auth()->id();
                $updates['cancelled_at'] = now();
            }

            $meeting->update($updates);

            // ثبت transition (append-only)
            MeetingStatusTransition::create([
                'meeting_id' => $meeting->id,
                'from_status' => $currentStatus->value,
                'to_status' => $newStatus->value,
                'reason' => $reason,
                'triggered_by_user_id' => auth()->id(),
                'on_behalf_of_user_id' => session('on_behalf_of_user_id'),
                'triggered_via' => $triggeredVia,
                'snapshot' => $snapshot,
                'occurred_at' => now(),
            ]);

            // audit
            $this->auditService->log(
                event: 'meeting_status_changed',
                auditable: $meeting,
                description: sprintf(
                    "وضعیت جلسه '%s' از '%s' به '%s' تغییر کرد",
                    $meeting->meeting_number,
                    $currentStatus->label(),
                    $newStatus->label(),
                ),
                oldValues: ['status' => $currentStatus->value],
                newValues: ['status' => $newStatus->value],
                context: ['reason' => $reason, 'via' => $triggeredVia],
                severity: $this->severityForTransition($newStatus),
            );

            // event
            event(new MeetingStatusChanged(
                meeting: $meeting->fresh(),
                previousStatus: $currentStatus,
                newStatus: $newStatus,
                reason: $reason,
            ));

            return $meeting->fresh();
        });
    }

    private function severityForTransition(MeetingStatus $to): string
    {
        return match ($to) {
            MeetingStatus::Cancelled => 'warning',
            MeetingStatus::Completed => 'notice',
            MeetingStatus::InProgress => 'info',
            default => 'info',
        };
    }
}
