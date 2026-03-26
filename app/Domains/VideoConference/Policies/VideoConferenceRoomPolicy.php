<?php

declare(strict_types=1);

namespace App\Domains\VideoConference\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\VideoConference\Models\VideoConferenceRoom;

class VideoConferenceRoomPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('vc_room.view');
    }

    public function view(User $user, VideoConferenceRoom $room): bool
    {
        if ($user->can('vc_room.view_all')) return true;
        if ($room->created_by_user_id === $user->id) return true;

        // اگر در meeting شرکت‌کننده است، می‌تواند ببیند
        if ($room->meeting && $user->employee) {
            return $room->meeting->participants()
                ->where('employee_id', $user->employee->id)
                ->exists();
        }
        return false;
    }

    public function create(User $user): bool
    {
        return $user->can('vc_room.create');
    }

    public function end(User $user, VideoConferenceRoom $room): bool
    {
        return $user->can('vc_room.end') || $room->created_by_user_id === $user->id;
    }

    public function record(User $user, VideoConferenceRoom $room): bool
    {
        return $user->can('vc_room.record');
    }

    public function join(User $user, VideoConferenceRoom $room): bool
    {
        return $this->view($user, $room) && $room->isActive();
    }
}
