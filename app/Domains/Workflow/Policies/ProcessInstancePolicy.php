<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\Workflow\Models\ProcessInstance;

class ProcessInstancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('process_instance.view');
    }

    public function view(User $user, ProcessInstance $instance): bool
    {
        if ($user->can('process_instance.view_all')) return true;

        return $user->can('process_instance.view')
            && (
                $instance->starter_user_id === $user->id
                || $instance->userTasks()
                    ->where(fn ($q) => $q
                        ->where('assignee_user_id', $user->id)
                        ->orWhereJsonContains('candidate_user_ids', $user->id),
                    )->exists()
            );
    }

    public function suspend(User $user, ProcessInstance $instance): bool
    {
        return $user->can('process_instance.suspend');
    }

    public function cancel(User $user, ProcessInstance $instance): bool
    {
        return $user->can('process_instance.cancel');
    }

    public function retry(User $user, ProcessInstance $instance): bool
    {
        return $user->can('process_instance.retry');
    }
}
