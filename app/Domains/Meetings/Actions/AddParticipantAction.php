<?php

declare(strict_types=1);

namespace App\Domains\Meetings\Actions;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Meetings\Enums\InvitationStatus;
use App\Domains\Meetings\Enums\ParticipantRole;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Models\MeetingParticipant;
use App\Domains\Organization\Models\Employee;
use Illuminate\Support\Facades\DB;

class AddParticipantAction
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {
    }

    /**
     * افزودن شرکت‌کننده داخلی (Employee)
     */
    public function addInternal(
        Meeting $meeting,
        int $employeeId,
        ParticipantRole $role = ParticipantRole::VotingMember,
        bool $isMandatory = true,
    ): MeetingParticipant {
        return DB::transaction(function () use ($meeting, $employeeId, $role, $isMandatory) {
            // duplicate check
            $existing = $meeting->participants()->where('employee_id', $employeeId)->first();
            if ($existing) {
                return $existing;
            }

            $employee = Employee::findOrFail($employeeId);

            $participant = MeetingParticipant::create([
                'meeting_id' => $meeting->id,
                'employee_id' => $employeeId,
                'user_id' => $employee->user_id,
                'role' => $role,
                'is_mandatory' => $isMandatory,
                'is_external' => false,
                'order_index' => ($meeting->participants()->max('order_index') ?? 0) + 1,
                'invitation_status' => InvitationStatus::NotInvited,
            ]);

            $this->auditService->log(
                event: 'participant_added',
                auditable: $meeting,
                description: sprintf(
                    "'%s' به جلسه '%s' در نقش '%s' افزوده شد",
                    $employee->full_name,
                    $meeting->meeting_number,
                    $role->label(),
                ),
                context: [
                    'employee_id' => $employeeId,
                    'role' => $role->value,
                    'is_mandatory' => $isMandatory,
                ],
                severity: 'info',
            );

            return $participant;
        });
    }

    /**
     * افزودن مهمان خارجی
     */
    public function addExternal(Meeting $meeting, array $data): MeetingParticipant
    {
        if (!$meeting->allow_external_participants) {
            throw new \DomainException('در این جلسه افراد خارج از سازمان مجاز نیستند.');
        }

        return DB::transaction(function () use ($meeting, $data) {
            $participant = MeetingParticipant::create([
                'meeting_id' => $meeting->id,
                'employee_id' => null,
                'external_full_name' => $data['full_name'],
                'external_email' => $data['email'] ?? null,
                'external_mobile' => $data['mobile'] ?? null,
                'external_organization' => $data['organization'] ?? null,
                'external_title' => $data['title'] ?? null,
                'external_national_code' => $data['national_code'] ?? null,
                'role' => ParticipantRole::from($data['role'] ?? 'guest'),
                'is_mandatory' => $data['is_mandatory'] ?? false,
                'is_external' => true,
                'order_index' => ($meeting->participants()->max('order_index') ?? 0) + 1,
                'invitation_status' => InvitationStatus::NotInvited,
            ]);

            $this->auditService->log(
                event: 'external_participant_added',
                auditable: $meeting,
                description: sprintf(
                    "مهمان خارجی '%s' به جلسه '%s' افزوده شد",
                    $data['full_name'],
                    $meeting->meeting_number,
                ),
                context: ['external_data' => $data],
                severity: 'info',
            );

            return $participant;
        });
    }
}
