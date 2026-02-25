<?php

declare(strict_types=1);

namespace App\Domains\Meetings\Actions;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Meetings\Enums\AttendanceStatus;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Models\MeetingParticipant;

/**
 * ثبت حضور و غیاب — معمولاً توسط دبیر در حین یا پس از جلسه
 */
class RecordAttendanceAction
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {
    }

    public function recordPresence(
        MeetingParticipant $participant,
        AttendanceStatus $status,
        ?\DateTimeInterface $joinedAt = null,
        ?\DateTimeInterface $leftAt = null,
    ): MeetingParticipant {
        if (!$participant->meeting->status->allowsAttendance()
            && !in_array($participant->meeting->status, [
                \App\Domains\Meetings\Enums\MeetingStatus::Completed,
            ], true)
        ) {
            throw new \DomainException(
                'در وضعیت فعلی جلسه، ثبت حضور مجاز نیست.'
            );
        }

        $updates = [
            'attendance_status' => $status,
        ];

        if ($joinedAt) {
            $updates['joined_at'] = $joinedAt;
        }
        if ($leftAt) {
            $updates['left_at'] = $leftAt;
        }

        if ($joinedAt && $leftAt) {
            $start = $joinedAt instanceof \DateTimeImmutable
                ? $joinedAt : new \DateTimeImmutable($joinedAt->format('c'));
            $end = $leftAt instanceof \DateTimeImmutable
                ? $leftAt : new \DateTimeImmutable($leftAt->format('c'));
            $updates['attendance_minutes'] = (int) (($end->getTimestamp() - $start->getTimestamp()) / 60);
        }

        $participant->update($updates);

        $this->auditService->log(
            event: 'attendance_recorded',
            auditable: $participant->meeting,
            description: sprintf(
                "حضور '%s' در جلسه '%s' به '%s' ثبت شد",
                $participant->display_name,
                $participant->meeting->meeting_number,
                $status->label(),
            ),
            context: [
                'participant_id' => $participant->id,
                'attendance_status' => $status->value,
                'attendance_minutes' => $updates['attendance_minutes'] ?? null,
            ],
            severity: 'info',
        );

        return $participant->fresh();
    }

    /**
     * ثبت bulk برای تمام شرکت‌کنندگان (معمولاً پس از پایان جلسه)
     *
     * @param array<int, string> $attendances key=participant_id, value=AttendanceStatus value
     */
    public function recordBulk(Meeting $meeting, array $attendances): int
    {
        $count = 0;
        foreach ($attendances as $participantId => $statusValue) {
            $participant = $meeting->participants()->find($participantId);
            if (!$participant) continue;

            $this->recordPresence(
                participant: $participant,
                status: AttendanceStatus::from($statusValue),
            );
            $count++;
        }
        return $count;
    }
}
