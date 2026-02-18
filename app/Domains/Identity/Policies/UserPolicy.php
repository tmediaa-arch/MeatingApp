<?php

declare(strict_types=1);

namespace App\Domains\Identity\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\AuthorizationService;

class UserPolicy
{
    public function __construct(
        private readonly AuthorizationService $authService,
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $this->authService->can($user, 'view', 'user');
    }

    public function view(User $user, User $target): bool
    {
        // خود کاربر همیشه پروفایل خود را می‌بیند
        if ($user->id === $target->id) {
            return true;
        }

        return $this->authService->can($user, 'view', $target);
    }

    public function create(User $user): bool
    {
        return $this->authService->can($user, 'create', 'user');
    }

    public function update(User $user, User $target): bool
    {
        // کاربر می‌تواند پروفایل خود را به‌روز کند (فیلدهای محدود)
        if ($user->id === $target->id) {
            return true;
        }

        // system users فقط توسط super-admin قابل تغییرند
        if ($target->is_system && !$user->hasRole('super-admin')) {
            return false;
        }

        return $this->authService->can($user, 'update', $target);
    }

    public function delete(User $user, User $target): bool
    {
        // کاربر نمی‌تواند خودش را حذف کند
        if ($user->id === $target->id) {
            return false;
        }

        if ($target->is_system) {
            return false;
        }

        return $this->authService->can($user, 'delete', $target);
    }

    public function suspend(User $user, User $target): bool
    {
        if ($user->id === $target->id) {
            return false;
        }

        if ($target->is_system) {
            return false;
        }

        return $this->authService->can($user, 'suspend', $target);
    }

    public function unlock(User $user, User $target): bool
    {
        return $this->authService->can($user, 'unlock', $target);
    }

    public function assignRole(User $user, User $target): bool
    {
        return $this->authService->can($user, 'assign-role', $target);
    }

    public function impersonate(User $user, User $target): bool
    {
        // فقط super-admin
        return $user->hasRole('super-admin') && $user->id !== $target->id;
    }
}
