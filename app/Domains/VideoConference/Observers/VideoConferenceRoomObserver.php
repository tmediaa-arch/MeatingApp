<?php

declare(strict_types=1);

namespace App\Domains\VideoConference\Observers;

use App\Domains\VideoConference\Enums\VideoConferenceRoomStatus;
use App\Domains\VideoConference\Exceptions\VideoConferenceException;
use App\Domains\VideoConference\Models\VideoConferenceRoom;

class VideoConferenceRoomObserver
{
    public function updating(VideoConferenceRoom $room): void
    {
        if (!$room->isDirty('status')) return;

        $old = $room->getOriginal('status');
        $new = $room->status;

        $oldEnum = $old instanceof VideoConferenceRoomStatus
            ? $old
            : VideoConferenceRoomStatus::tryFrom((string) $old);
        $newEnum = $new instanceof VideoConferenceRoomStatus
            ? $new
            : VideoConferenceRoomStatus::tryFrom((string) $new);

        if ($oldEnum && $newEnum && !$oldEnum->canTransitionTo($newEnum)) {
            throw VideoConferenceException::invalidTransition($oldEnum->value, $newEnum->value);
        }
    }
}
