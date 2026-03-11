<?php

declare(strict_types=1);

namespace App\Domains\Tasks\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\Tasks\Models\Task;

class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('task.view');
    }

    public function view(User $user, Task $task): bool
    {
        return $task->canBeViewedBy($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('task.create');
    }

    public function update(User $user, Task $task): bool
    {
        return $task->canBeUpdatedBy($user);
    }

    public function assign(User $user, Task $task): bool
    {
        return $user->hasPermissionTo('task.assign');
    }

    public function submit(User $user, Task $task): bool
    {
        if ($task->status->isTerminal()) return false;
        return $task->assignee_user_id === $user->id
            || $task->assignee_employee_id === $user->employee_id
            || $user->hasRole('super-admin');
    }

    public function approve(User $user, Task $task): bool
    {
        if (!$user->hasPermissionTo('task.approve')) return false;
        return $task->approver_employee_id === $user->employee_id
            || $user->hasRole('super-admin');
    }

    public function requestExtension(User $user, Task $task): bool
    {
        if ($task->status->isTerminal()) return false;
        return $task->assignee_user_id === $user->id
            || $task->assignee_employee_id === $user->employee_id;
    }

    public function reviewExtension(User $user, Task $task): bool
    {
        if (!$user->hasPermissionTo('task.extend')) return false;
        return $task->supervisor_employee_id === $user->employee_id
            || $task->approver_employee_id === $user->employee_id
            || $user->hasRole('super-admin');
    }

    public function delete(User $user, Task $task): bool
    {
        if (!$user->hasPermissionTo('task.delete')) return false;
        return $task->status->value === 'open';
    }
}
