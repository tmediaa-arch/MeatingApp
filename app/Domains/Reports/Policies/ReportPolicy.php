<?php

declare(strict_types=1);

namespace App\Domains\Reports\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\Reports\Models\Report;

class ReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['report.view', 'report.view_all']);
    }

    public function view(User $user, Report $report): bool
    {
        if ($user->hasPermissionTo('report.view_all')) {
            return true;
        }

        if (!$user->clearanceLevel()->canAccess($report->confidentiality_level)) {
            return false;
        }

        return $user->hasPermissionTo('report.view');
    }

    public function run(User $user, Report $report): bool
    {
        return $this->view($user, $report) && $user->hasPermissionTo('report.run');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('report.create');
    }

    public function update(User $user, Report $report): bool
    {
        if ($report->is_system) {
            return false; // system reports قابل ویرایش نیستند
        }
        return $user->hasPermissionTo('report.update');
    }

    public function delete(User $user, Report $report): bool
    {
        if ($report->is_system) {
            return false;
        }
        return $user->hasPermissionTo('report.delete');
    }

    public function schedule(User $user, Report $report): bool
    {
        return $this->run($user, $report) && $user->hasPermissionTo('report.schedule');
    }
}
