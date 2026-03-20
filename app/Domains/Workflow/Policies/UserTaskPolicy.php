<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\Workflow\Models\UserTask;

class UserTaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('user_task.view');
    }

    public function view(User $user, UserTask $task): bool
    {
        if ($user->can('user_task.view_all')) return true;
        return $task->canBeClaimedBy($user) || $task->completed_by_user_id === $user->id;
    }

    public function claim(User $user, UserTask $task): bool
    {
        return $user->can('user_task.claim') && $task->canBeClaimedBy($user);
    }

    public function complete(User $user, UserTask $task): bool
    {
        return $user->can('user_task.complete') && $task->canBeCompletedBy($user);
    }

    public function reassign(User $user, UserTask $task): bool
    {
        return $user->can('user_task.reassign');
    }
}
