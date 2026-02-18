<?php

declare(strict_types=1);

namespace App\Domains\Organization\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\AuthorizationService;
use App\Domains\Organization\Models\Employee;

class EmployeePolicy
{
    public function __construct(
        private readonly AuthorizationService $authService,
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $this->authService->can($user, 'view', 'employee');
    }

    public function view(User $user, Employee $employee): bool
    {
        // کاربر همیشه employee مرتبط با خود را می‌بیند
        if ($user->employee_id === $employee->id) {
            return true;
        }

        return $this->authService->can($user, 'view', $employee);
    }

    public function create(User $user): bool
    {
        return $this->authService->can($user, 'create', 'employee');
    }

    public function update(User $user, Employee $employee): bool
    {
        return $this->authService->can($user, 'update', $employee);
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $this->authService->can($user, 'delete', $employee);
    }

    public function transfer(User $user, Employee $employee): bool
    {
        return $this->authService->can($user, 'transfer', $employee);
    }

    public function assignPosition(User $user, Employee $employee): bool
    {
        return $this->authService->can($user, 'assign-position', $employee);
    }
}
