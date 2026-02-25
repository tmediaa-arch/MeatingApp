<?php

declare(strict_types=1);

namespace App\Domains\Meetings\Actions;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Meetings\Exceptions\MeetingException;
use App\Domains\Meetings\Models\Meeting;
use Illuminate\Support\Facades\DB;

/**
 * Action برای به‌روزرسانی فیلدهای عمومی جلسه.
 *
 * این Action فقط فیلدهای متادیتایی (موضوع، توضیحات، تنظیمات) را تغییر می‌دهد.
 * تغییر زمان: از RescheduleMeetingAction استفاده شود
 * تغییر وضعیت: از TransitionMeetingStatusAction استفاده شود
 * مدیریت شرکت‌کنندگان: از AddParticipantAction/RemoveParticipantAction
 */
class UpdateMeetingAction
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {
    }

    /**
     * فیلدهای قابل تغییر — تنها همین فیلدها بررسی می‌شوند
     */
    private const UPDATABLE_FIELDS = [
        'subject',
        'description',
        'agenda_items',
        'type',
        'mode',
        'confidentiality_level',
        'location_alt',
        'chairperson_employee_id',
        'secretary_employee_id',
        'allow_external_participants',
        'require_confirmation',
        'record_attendance',
        'send_reminder',
        'reminder_minutes_before',
        'allow_late_join',
        'tags',
        'metadata',
    ];

    public function execute(Meeting $meeting, array $data): Meeting
    {
        if (!$meeting->status->isEditable()) {
            throw MeetingException::cannotEditInStatus($meeting->status);
        }

        $updates = array_intersect_key($data, array_flip(self::UPDATABLE_FIELDS));

        if (empty($updates)) {
            return $meeting;
        }

        return DB::transaction(function () use ($meeting, $updates) {
            $oldValues = array_intersect_key($meeting->getAttributes(), $updates);

            $meeting->update($updates);

            $this->auditService->log(
                event: 'meeting_updated',
                auditable: $meeting,
                description: sprintf(
                    "جلسه '%s' به‌روزرسانی شد",
                    $meeting->meeting_number,
                ),
                oldValues: $oldValues,
                newValues: $updates,
                severity: 'info',
            );

            return $meeting->fresh();
        });
    }
}
