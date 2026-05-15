<?php

declare(strict_types=1);

namespace App\Domains\Rooms\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\Rooms\Models\RoomReservation;

class RoomReservationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('reservation.view');
    }

    public function view(User $user, RoomReservation $reservation): bool
    {
        return $user->can('reservation.view');
    }

    public function create(User $user): bool
    {
        return $user->can('reservation.create');
    }

    public function update(User $user, RoomReservation $reservation): bool
    {
        return $user->can('reservation.create');
    }

    public function approve(User $user, RoomReservation $reservation): bool
    {
        return $user->can('reservation.approve');
    }

    public function delete(User $user, RoomReservation $reservation): bool
    {
        return $user->can('reservation.approve');
    }
}
