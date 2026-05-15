<?php

declare(strict_types=1);

namespace App\Domains\Invitations\Actions;

use App\Domains\Meetings\Enums\InvitationStatus;
use App\Domains\Meetings\Models\MeetingParticipant;
use Illuminate\Support\Facades\DB;

/**
 * RespondToInvitationAction — ثبت پاسخ یک شرکت‌کننده به دعوت جلسه.
 */
class RespondToInvitationAction
{
    public function execute(
        MeetingParticipant $participant,
        string $response,
        ?string $note = null,
        ?int $respondedByUserId = null,
    ): MeetingParticipant {
        return DB::transaction(function () use ($participant, $response, $note) {
            $participant->update([
                'invitation_status' => InvitationStatus::from($response),
                'invitation_responded_at' => now(),
                'response_note' => $note,
            ]);

            return $participant->refresh();
        });
    }
}
