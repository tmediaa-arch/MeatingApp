<?php

declare(strict_types=1);

namespace App\Domains\Minutes\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\Minutes\Enums\MinuteStatus;
use App\Domains\Minutes\Models\Minute;

class MinutePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('minute.view');
    }

    public function view(User $user, Minute $minute): bool
    {
        return $minute->canBeViewedBy($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('minute.create');
    }

    public function update(User $user, Minute $minute): bool
    {
        return $minute->canBeEditedBy($user);
    }

    public function delete(User $user, Minute $minute): bool
    {
        if (!$user->hasPermissionTo('minute.delete')) return false;
        return $minute->status === MinuteStatus::Draft;
    }

    public function signAsSecretary(User $user, Minute $minute): bool
    {
        if (!$user->employee_id) return false;
        if ($user->employee_id !== $minute->secretary_employee_id) return false;
        return $minute->status === MinuteStatus::Review;
    }

    public function signAsChairperson(User $user, Minute $minute): bool
    {
        if (!$user->employee_id) return false;
        if ($user->employee_id !== $minute->chairperson_employee_id) return false;
        return in_array($minute->status, [MinuteStatus::Review, MinuteStatus::Signed], true);
    }

    public function publish(User $user, Minute $minute): bool
    {
        if (!$user->hasPermissionTo('minute.publish')) return false;
        return $minute->status === MinuteStatus::Signed && $minute->isFullySigned();
    }

    public function revoke(User $user, Minute $minute): bool
    {
        if (!$user->hasPermissionTo('minute.revoke')) return false;
        return $minute->status === MinuteStatus::Published;
    }
}
