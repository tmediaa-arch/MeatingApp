<?php

declare(strict_types=1);

namespace App\Domains\Organization\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\AuthorizationService;
use App\Domains\Organization\Models\OrgUnit;

class OrgUnitPolicy
{
    public function __construct(
        private readonly AuthorizationService $authService,
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $this->authService->can($user, 'view', 'orgunit');
    }

    public function view(User $user, OrgUnit $orgUnit): bool
    {
        return $this->authService->can($user, 'view', $orgUnit);
    }

    public function create(User $user): bool
    {
        return $this->authService->can($user, 'create', 'orgunit');
    }

    public function update(User $user, OrgUnit $orgUnit): bool
    {
        return $this->authService->can($user, 'update', $orgUnit);
    }

    public function delete(User $user, OrgUnit $orgUnit): bool
    {
        return $this->authService->can($user, 'delete', $orgUnit);
    }

    public function move(User $user, OrgUnit $orgUnit): bool
    {
        return $this->authService->can($user, 'move', $orgUnit);
    }
}
