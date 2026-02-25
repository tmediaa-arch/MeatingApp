<?php

declare(strict_types=1);

namespace App\Domains\Meetings\Actions;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Invitations\Models\Invitation;
use App\Domains\Meetings\Enums\InvitationStatus;
use App\Domains\Meetings\Enums\MeetingStatus;
use App\Domains\Meetings\Exceptions\MeetingException;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Models\MeetingParticipant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ارسال دعوت‌نامه‌های اولیه برای جلسه.
 *
 * منطق:
 * 1. اعتبارسنجی (وضعیت جلسه باید Scheduled یا InvitationsSent باشد)
 * 2. برای هر participant، یک Invitation در صف ایجاد شود (برای ایمیل و SMS)
 * 3. اگر send_reminder=true، invitation اضافی reminder برنامه‌ریزی می‌شود
 * 4. وضعیت جلسه به InvitationsSent تغییر کند
 * 5. وضعیت participant ها به Invited
 *
 * ارسال واقعی پیام‌ها در Job های Phase 3 انجام می‌شود.
 */
class SendInvitationsAction
{
    public function __construct(
        private readonly TransitionMeetingStatusAction $transitionAction,
        private readonly AuditService $auditService,
    ) {
    }

    /**
     * @param array<string> $channels کانال‌های ارسال: email|sms|in_app
     * @return int تعداد invitation های ایجاد شده
     */
    public function execute(Meeting $meeting, array $channels = ['email', 'in_app']): int
    {
        if (!in_array($meeting->status, [
            MeetingStatus::Scheduled,
            MeetingStatus::InvitationsSent,
        ], true)) {
            throw MeetingException::cannotEditInStatus($meeting->status);
        }

        return DB::transaction(function () use ($meeting, $channels) {
            $count = 0;

            $participants = $meeting->participants()
                ->whereIn('invitation_status', [
                    InvitationStatus::NotInvited,
                ])
                ->get();

            foreach ($participants as $participant) {
                $count += $this->createInvitationsForParticipant($meeting, $participant, $channels);

                // به‌روزرسانی وضعیت participant
                $participant->update([
                    'invitation_status' => InvitationStatus::Invited,
                ]);
            }

            // transition جلسه اگر هنوز Scheduled است
            if ($meeting->status === MeetingStatus::Scheduled) {
                $this->transitionAction->execute(
                    meeting: $meeting,
                    newStatus: MeetingStatus::InvitationsSent,
                    reason: 'ارسال دعوت‌نامه‌ها',
                );
            }

            $this->auditService->log(
                event: 'invitations_sent',
                auditable: $meeting,
                description: sprintf(
                    "ارسال %d دعوت‌نامه برای جلسه '%s' در صف قرار گرفت",
                    $count,
                    $meeting->meeting_number,
                ),
                context: [
                    'invitations_count' => $count,
                    'participants_count' => $participants->count(),
                    'channels' => $channels,
                ],
                severity: 'notice',
            );

            return $count;
        });
    }

    private function createInvitationsForParticipant(
        Meeting $meeting,
        MeetingParticipant $participant,
        array $channels,
    ): int {
        $count = 0;

        foreach ($channels as $channel) {
            $toAddress = $this->resolveAddress($participant, $channel);
            if (!$toAddress) continue;

            // invitation اصلی
            Invitation::create([
                'meeting_id' => $meeting->id,
                'participant_id' => $participant->id,
                'type' => 'invitation',
                'channel' => $channel,
                'to_address' => $toAddress,
                'subject' => sprintf('دعوت به جلسه: %s', $meeting->subject),
                'body' => $this->renderInvitationBody($meeting, $participant),
                'ical_uid' => $channel === 'email' ? $this->generateIcalUid($meeting, $participant) : null,
                'ical_sequence' => 0,
                'status' => 'queued',
                'response_token' => Str::random(40),
                'response_token_expires_at' => $meeting->scheduled_start_at->copy(),
            ]);

            $count++;

            // reminder
            if ($meeting->send_reminder && $meeting->reminder_minutes_before > 0) {
                $reminderTime = $meeting->scheduled_start_at
                    ->copy()
                    ->subMinutes($meeting->reminder_minutes_before);

                if ($reminderTime > now()) {
                    Invitation::create([
                        'meeting_id' => $meeting->id,
                        'participant_id' => $participant->id,
                        'type' => 'reminder',
                        'channel' => $channel,
                        'to_address' => $toAddress,
                        'subject' => sprintf('یادآوری جلسه: %s', $meeting->subject),
                        'body' => $this->renderReminderBody($meeting, $participant),
                        'status' => 'queued',
                        'scheduled_at' => $reminderTime,
                    ]);
                    $count++;
                }
            }
        }

        return $count;
    }

    private function resolveAddress(MeetingParticipant $participant, string $channel): ?string
    {
        return match ($channel) {
            'email' => $participant->email,
            'sms' => $participant->mobile,
            'in_app' => $participant->user_id ? (string) $participant->user_id : null,
            'push' => $participant->user_id ? (string) $participant->user_id : null,
            'ical' => $participant->email,
            default => null,
        };
    }

    private function renderInvitationBody(Meeting $meeting, MeetingParticipant $participant): string
    {
        // در فاز ۳ از Notification Templates استفاده می‌شود
        // در اینجا placeholder ساده
        $name = $participant->display_name;
        $jalaliService = app(\App\Domains\Calendar\Services\JalaliCalendarService::class);
        $startStr = $jalaliService->formatHuman($meeting->scheduled_start_at);

        return sprintf(
            "%s گرامی،\n\nبدینوسیله از حضور شما در جلسه «%s» به شرح زیر دعوت می‌شود:\n\nزمان: %s\nمکان: %s\nنقش شما: %s\n",
            $name,
            $meeting->subject,
            $startStr,
            $meeting->room?->name ?? $meeting->location_alt ?? 'آنلاین',
            $participant->role->label(),
        );
    }

    private function renderReminderBody(Meeting $meeting, MeetingParticipant $participant): string
    {
        $jalaliService = app(\App\Domains\Calendar\Services\JalaliCalendarService::class);
        $startStr = $jalaliService->formatHuman($meeting->scheduled_start_at);

        return sprintf(
            "یادآوری: جلسه «%s» در %s برگزار خواهد شد.",
            $meeting->subject,
            $startStr,
        );
    }

    private function generateIcalUid(Meeting $meeting, MeetingParticipant $participant): string
    {
        return sprintf(
            'mms-%d-%d-%s@%s',
            $meeting->id,
            $participant->id,
            Str::random(8),
            config('app.url', 'mms.local'),
        );
    }
}
