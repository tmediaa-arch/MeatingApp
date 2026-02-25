<?php

declare(strict_types=1);

namespace App\Domains\Meetings\Actions;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Meetings\Enums\MeetingStatus;
use App\Domains\Meetings\Events\MeetingCancelled;
use App\Domains\Meetings\Exceptions\MeetingException;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Rooms\Actions\CancelReservationAction;
use Illuminate\Support\Facades\DB;

/**
 * Action برای لغو جلسه — wrapper بالاسر TransitionMeetingStatusAction
 * که علاوه بر تغییر وضعیت:
 * - رزرو سالن را لغو می‌کند
 * - دعوت‌نامه‌های در صف ارسال را cancel می‌کند
 * - رخداد MeetingCancelled را پخش می‌کند
 *
 * (ارسال اعلان لغو به مدعوین در فاز ۳ از طریق listener)
 */
class CancelMeetingAction
{
    public function __construct(
        private readonly TransitionMeetingStatusAction $transitionAction,
        private readonly CancelReservationAction $cancelReservationAction,
        private readonly AuditService $auditService,
    ) {
    }

    public function execute(Meeting $meeting, string $reason): Meeting
    {
        if ($meeting->status->isTerminal()) {
            throw MeetingException::cannotCancelTerminal($meeting->status);
        }

        return DB::transaction(function () use ($meeting, $reason) {
            // 1. transition وضعیت
            $cancelled = $this->transitionAction->execute(
                meeting: $meeting,
                newStatus: MeetingStatus::Cancelled,
                reason: $reason,
            );

            // 2. لغو رزرو سالن (در صورت وجود)
            if ($meeting->reservation) {
                $this->cancelReservationAction->execute(
                    reservation: $meeting->reservation,
                    reason: 'لغو جلسه: ' . $reason,
                );
            }

            // 3. cancel کردن invitation های در صف
            \App\Domains\Invitations\Models\Invitation::query()
                ->where('meeting_id', $meeting->id)
                ->where('status', 'queued')
                ->update([
                    'status' => 'failed',
                    'error_message' => 'جلسه لغو شد',
                ]);

            // 4. event
            event(new MeetingCancelled(
                meeting: $cancelled,
                reason: $reason,
                cancelledBy: auth()->user(),
            ));

            return $cancelled;
        });
    }
}
