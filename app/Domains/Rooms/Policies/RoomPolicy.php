<?php

declare(strict_types=1);

namespace App\Domains\Rooms\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\Rooms\Models\Room;

class RoomPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('reservation.view') || $user->can('room.view');
    }

    public function view(User $user, Room $room): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('room.manage');
    }

    public function update(User $user, Room $room): bool
    {
        return $user->can('room.manage');
    }

    public function delete(User $user, Room $room): bool
    {
        return $user->can('room.manage');
    }
}
