<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\Workflow\Enums\ProcessDefinitionStatus;
use App\Domains\Workflow\Models\ProcessDefinition;

class ProcessDefinitionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('process.view');
    }

    public function view(User $user, ProcessDefinition $definition): bool
    {
        return $user->can('process.view')
            && $definition->organization_id === $user->employee?->organization_id;
    }

    public function create(User $user): bool
    {
        return $user->can('process.create');
    }

    public function update(User $user, ProcessDefinition $definition): bool
    {
        return $user->can('process.update')
            && $definition->status === ProcessDefinitionStatus::Draft;
    }

    public function deploy(User $user): bool
    {
        return $user->can('process.deploy');
    }

    public function publish(User $user, ProcessDefinition $definition): bool
    {
        return $user->can('process.publish')
            && $definition->status === ProcessDefinitionStatus::Draft;
    }

    public function archive(User $user, ProcessDefinition $definition): bool
    {
        return $user->can('process.archive');
    }

    public function delete(User $user, ProcessDefinition $definition): bool
    {
        return $user->can('process.delete')
            && $definition->status !== ProcessDefinitionStatus::Published;
    }
}
