<?php

declare(strict_types=1);

namespace App\Domains\Meetings\Observers;

use App\Domains\Meetings\Enums\MeetingStatus;
use App\Domains\Meetings\Models\Meeting;

class MeetingObserver
{
    /**
     * هنگام به‌روزرسانی، اگر status تغییر کرد و این تغییر مستقیم بود (نه از TransitionAction)،
     * جلوگیری می‌کنیم. این یک محافظ defence-in-depth است.
     *
     * در واقعیت، TransitionMeetingStatusAction status را با Model::update تغییر می‌دهد،
     * که این observer را هم trigger می‌کند — ما نمی‌توانیم تفاوت را تشخیص دهیم.
     * در صورت نیاز به enforcement سخت‌تر، یک flag در $meeting->isTransitioning می‌توانست
     * توسط TransitionAction set شود. در این فاز، بر روی documentation و Action-only تکیه می‌کنیم.
     */
    public function updating(Meeting $meeting): void
    {
        // اگر status تغییر کرده، انتقال باید valid باشد
        if ($meeting->isDirty('status')) {
            $originalValue = $meeting->getOriginal('status');
            $newValue = $meeting->getAttribute('status');

            // status ها در این مرحله می‌توانند string، یا enum باشند
            $originalStatus = $originalValue instanceof MeetingStatus
                ? $originalValue
                : MeetingStatus::from($originalValue);

            $newStatus = $newValue instanceof MeetingStatus
                ? $newValue
                : MeetingStatus::from($newValue);

            if (!$originalStatus->canTransitionTo($newStatus)) {
                throw new \LogicException(sprintf(
                    'Invalid status transition from %s to %s. Use TransitionMeetingStatusAction.',
                    $originalStatus->value,
                    $newStatus->value,
                ));
            }
        }
    }

    /**
     * در صورت حذف soft، رزرو سالن را cancel کنیم
     */
    public function deleting(Meeting $meeting): void
    {
        if ($meeting->reservation && $meeting->reservation->status->blocksRoom()) {
            $meeting->reservation->update([
                'status' => \App\Domains\Rooms\Enums\ReservationStatus::Cancelled,
                'metadata' => array_merge($meeting->reservation->metadata ?? [], [
                    'cancelled_due_to_meeting_deletion' => true,
                    'cancelled_at' => now()->toIso8601String(),
                ]),
            ]);
        }
    }
}
