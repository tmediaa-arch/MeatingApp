<?php

declare(strict_types=1);

namespace App\Domains\Dashboards\Policies;

use App\Domains\Dashboards\Models\Dashboard;
use App\Domains\Identity\Models\User;

class DashboardPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('dashboard.view');
    }

    public function view(User $user, Dashboard $dashboard): bool
    {
        if ($user->can('dashboard.manage')) {
            return true;
        }

        return $user->can('dashboard.view') && $dashboard->canBeViewedBy($user);
    }

    public function create(User $user): bool
    {
        return $user->can('dashboard.manage');
    }

    public function update(User $user, Dashboard $dashboard): bool
    {
        return $user->can('dashboard.manage');
    }

    public function delete(User $user, Dashboard $dashboard): bool
    {
        return $user->can('dashboard.manage');
    }
}
