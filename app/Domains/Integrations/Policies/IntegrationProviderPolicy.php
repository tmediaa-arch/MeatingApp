<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\Integrations\Models\IntegrationProvider;

class IntegrationProviderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('integration.view');
    }

    public function view(User $user, IntegrationProvider $provider): bool
    {
        return $user->can('integration.view');
    }

    public function create(User $user): bool
    {
        return $user->can('integration.manage');
    }

    public function update(User $user, IntegrationProvider $provider): bool
    {
        return $user->can('integration.manage');
    }

    public function delete(User $user, IntegrationProvider $provider): bool
    {
        return $user->can('integration.manage');
    }

    public function sync(User $user, IntegrationProvider $provider): bool
    {
        return $user->can('integration.sync');
    }
}
