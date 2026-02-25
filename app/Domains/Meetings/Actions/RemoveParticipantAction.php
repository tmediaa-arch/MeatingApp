<?php

declare(strict_types=1);

namespace App\Domains\Meetings\Actions;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Meetings\Enums\ParticipantRole;
use App\Domains\Meetings\Models\MeetingParticipant;

class RemoveParticipantAction
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {
    }

    public function execute(MeetingParticipant $participant, ?string $reason = null): void
    {
        // اجازه نمی‌دهیم رئیس یا دبیر را به این روش حذف کنیم
        if (in_array($participant->role, [
            ParticipantRole::Chairperson,
            ParticipantRole::Secretary,
        ], true)) {
            throw new \DomainException(
                'برای حذف رئیس یا دبیر جلسه، ابتدا آن‌ها را در جلسه تغییر دهید.'
            );
        }

        $meeting = $participant->meeting;
        $name = $participant->display_name;

        $this->auditService->log(
            event: 'participant_removed',
            auditable: $meeting,
            description: sprintf(
                "'%s' از جلسه '%s' حذف شد",
                $name,
                $meeting->meeting_number,
            ),
            context: [
                'participant_id' => $participant->id,
                'role' => $participant->role->value,
                'reason' => $reason,
            ],
            severity: 'notice',
        );

        $participant->delete();
    }
}
