<?php

declare(strict_types=1);

namespace App\Domains\Meetings\Actions;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Calendar\ValueObjects\TimeRange;
use App\Domains\Meetings\DTOs\CreateMeetingData;
use App\Domains\Meetings\Enums\InvitationStatus;
use App\Domains\Meetings\Enums\MeetingStatus;
use App\Domains\Meetings\Enums\ParticipantRole;
use App\Domains\Meetings\Events\MeetingCreated;
use App\Domains\Meetings\Exceptions\MeetingException;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Models\MeetingParticipant;
use App\Domains\Meetings\Models\MeetingStatusTransition;
use App\Domains\Organization\Models\Employee;
use App\Domains\Rooms\Actions\ReserveRoomAction;
use App\Domains\Rooms\Models\Room;
use Illuminate\Support\Facades\DB;

/**
 * هسته ایجاد جلسه.
 *
 * مسئولیت‌ها:
 * 1. اعتبارسنجی پایه (که در DTO نبوده)
 * 2. تولید meeting_number
 * 3. ایجاد Meeting در وضعیت Draft
 * 4. ایجاد participant ها (داخلی + خارجی)
 * 5. ایجاد agenda items
 * 6. در صورت تعیین سالن، رزرو سالن از طریق ReserveRoomAction
 * 7. ثبت transition اولیه
 * 8. ثبت در audit log
 * 9. پخش رخداد MeetingCreated
 */
class CreateMeetingAction
{
    public function __construct(
        private readonly AuditService $auditService,
        private readonly ReserveRoomAction $reserveRoomAction,
    ) {
    }

    public function execute(CreateMeetingData $data): Meeting
    {
        $this->validate($data);

        return DB::transaction(function () use ($data) {
            // 1. تولید meeting_number
            $meetingNumber = Meeting::generateMeetingNumber($data->organization_id);

            // 2. ایجاد جلسه در وضعیت Draft
            $meeting = Meeting::create([
                'organization_id' => $data->organization_id,
                'host_org_unit_id' => $data->host_org_unit_id,
                'meeting_number' => $meetingNumber,
                'subject' => $data->subject,
                'description' => $data->description,
                'agenda_items' => $data->agenda_items,
                'type' => $data->type,
                'mode' => $data->mode,
                'recurrence_pattern' => $data->recurrence_pattern,
                'recurrence_config' => $data->recurrence_config,
                'confidentiality_level' => $data->confidentiality_level,
                'scheduled_start_at' => $data->scheduled_start_at,
                'scheduled_end_at' => $data->scheduled_end_at,
                'timezone' => $data->timezone,
                'room_id' => $data->room_id,
                'location_alt' => $data->location_alt,
                'chairperson_employee_id' => $data->chairperson_employee_id,
                'secretary_employee_id' => $data->secretary_employee_id,
                'creator_user_id' => $data->creator_user_id ?? auth()->id(),
                'status' => MeetingStatus::Draft,
                'allow_external_participants' => $data->allow_external_participants,
                'require_confirmation' => $data->require_confirmation,
                'record_attendance' => $data->record_attendance,
                'send_reminder' => $data->send_reminder,
                'reminder_minutes_before' => $data->reminder_minutes_before,
                'allow_late_join' => $data->allow_late_join,
                'tags' => $data->tags,
                'metadata' => $data->metadata,
            ]);

            // 3. ایجاد participant ها — رئیس و دبیر هم به‌عنوان participant
            $this->addKeyParticipants($meeting, $data);
            $this->addRegularParticipants($meeting, $data);
            $this->addExternalParticipants($meeting, $data);

            // 4. در صورت تعیین سالن، رزرو
            if ($data->room_id) {
                $room = Room::findOrFail($data->room_id);
                $this->reserveRoomAction->execute(
                    room: $room,
                    meeting: $meeting,
                    range: TimeRange::from($data->scheduled_start_at, $data->scheduled_end_at),
                    purpose: $data->subject,
                    expectedAttendees: count($data->participants) + count($data->external_participants) + 2,
                );
            }

            // 5. ثبت transition اولیه
            MeetingStatusTransition::create([
                'meeting_id' => $meeting->id,
                'from_status' => null,
                'to_status' => MeetingStatus::Draft->value,
                'reason' => 'ایجاد اولیه',
                'triggered_by_user_id' => auth()->id(),
                'triggered_via' => 'manual',
                'occurred_at' => now(),
            ]);

            // 6. audit log
            $this->auditService->log(
                event: 'meeting_created',
                auditable: $meeting,
                description: sprintf(
                    "جلسه '%s' (%s) ایجاد شد",
                    $meeting->subject,
                    $meeting->meeting_number,
                ),
                context: [
                    'meeting_number' => $meeting->meeting_number,
                    'mode' => $meeting->mode->value,
                    'type' => $meeting->type->value,
                    'confidentiality' => $meeting->confidentiality_level->value,
                    'participants_count' => count($data->participants) + count($data->external_participants),
                ],
                severity: 'notice',
            );

            // 7. event
            event(new MeetingCreated($meeting, auth()->user()));

            return $meeting->fresh(['participants', 'room']);
        });
    }

    private function validate(CreateMeetingData $data): void
    {
        // 1. زمان منطقی
        if ($data->scheduled_end_at <= $data->scheduled_start_at) {
            throw MeetingException::invalidScheduleRange();
        }

        if ($data->scheduled_start_at < now()) {
            throw MeetingException::scheduleInPast();
        }

        // 2. برای جلسات هیئت‌مدیره و مجمع، رئیس جلسه الزامی است
        if (in_array($data->type->value, ['board', 'general_assembly'], true)
            && empty($data->chairperson_employee_id)
        ) {
            throw MeetingException::noChairpersonForKeyMeeting();
        }

        // 3. شرکت‌کنندگان خارجی فقط اگر اجازه داده شده
        if (!$data->allow_external_participants && !empty($data->external_participants)) {
            throw MeetingException::externalParticipantsNotAllowed();
        }
    }

    private function addKeyParticipants(Meeting $meeting, CreateMeetingData $data): void
    {
        $orderIndex = 0;

        if ($data->chairperson_employee_id) {
            MeetingParticipant::create([
                'meeting_id' => $meeting->id,
                'employee_id' => $data->chairperson_employee_id,
                'role' => ParticipantRole::Chairperson,
                'is_mandatory' => true,
                'is_external' => false,
                'order_index' => $orderIndex++,
                'invitation_status' => InvitationStatus::NotInvited,
            ]);
        }

        if ($data->secretary_employee_id && $data->secretary_employee_id !== $data->chairperson_employee_id) {
            MeetingParticipant::create([
                'meeting_id' => $meeting->id,
                'employee_id' => $data->secretary_employee_id,
                'role' => ParticipantRole::Secretary,
                'is_mandatory' => true,
                'is_external' => false,
                'order_index' => $orderIndex++,
                'invitation_status' => InvitationStatus::NotInvited,
            ]);
        }
    }

    private function addRegularParticipants(Meeting $meeting, CreateMeetingData $data): void
    {
        $existingIds = $meeting->participants()->pluck('employee_id')->filter()->all();
        $orderIndex = $meeting->participants()->max('order_index') ?? 0;
        $orderIndex++;

        foreach ($data->participants as $p) {
            $employeeId = $p['employee_id'] ?? null;
            if (!$employeeId) continue;

            // duplicate prevention
            if (in_array($employeeId, $existingIds, true)) {
                continue;
            }

            $employee = Employee::find($employeeId);
            if (!$employee) continue;

            MeetingParticipant::create([
                'meeting_id' => $meeting->id,
                'employee_id' => $employeeId,
                'user_id' => $employee->user_id,
                'role' => ParticipantRole::from($p['role'] ?? 'voting_member'),
                'is_mandatory' => $p['is_mandatory'] ?? true,
                'is_external' => false,
                'order_index' => $orderIndex++,
                'invitation_status' => InvitationStatus::NotInvited,
            ]);

            $existingIds[] = $employeeId;
        }
    }

    private function addExternalParticipants(Meeting $meeting, CreateMeetingData $data): void
    {
        $orderIndex = ($meeting->participants()->max('order_index') ?? 0) + 1;

        foreach ($data->external_participants as $ext) {
            if (empty($ext['full_name'])) continue;

            MeetingParticipant::create([
                'meeting_id' => $meeting->id,
                'employee_id' => null,
                'external_full_name' => $ext['full_name'],
                'external_email' => $ext['email'] ?? null,
                'external_mobile' => $ext['mobile'] ?? null,
                'external_organization' => $ext['organization'] ?? null,
                'external_title' => $ext['title'] ?? null,
                'external_national_code' => $ext['national_code'] ?? null,
                'role' => ParticipantRole::from($ext['role'] ?? 'guest'),
                'is_mandatory' => $ext['is_mandatory'] ?? false,
                'is_external' => true,
                'order_index' => $orderIndex++,
                'invitation_status' => InvitationStatus::NotInvited,
            ]);
        }
    }
}
