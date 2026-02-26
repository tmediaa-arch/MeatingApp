<?php

declare(strict_types=1);

namespace App\Domains\Meetings\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\AuthorizationService;
use App\Domains\Meetings\Models\Meeting;

class MeetingPolicy
{
    public function __construct(
        private readonly AuthorizationService $authService,
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['meeting.view', 'meeting.view_own', 'meeting.view_all']);
    }

    public function view(User $user, Meeting $meeting): bool
    {
        // ادمین کل
        if ($user->hasPermissionTo('meeting.view_all')) {
            return true;
        }

        return $meeting->canBeViewedBy($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('meeting.create');
    }

    public function update(User $user, Meeting $meeting): bool
    {
        if (!$user->hasPermissionTo('meeting.update')) {
            return false;
        }

        return $meeting->canBeEditedBy($user);
    }

    public function delete(User $user, Meeting $meeting): bool
    {
        // فقط draft قابل حذف — برای بقیه از cancel استفاده می‌شود
        if ($meeting->status !== \App\Domains\Meetings\Enums\MeetingStatus::Draft) {
            return false;
        }

        return $user->hasPermissionTo('meeting.delete')
            && ($meeting->creator_user_id === $user->id || $user->hasRole('admin'));
    }

    public function cancel(User $user, Meeting $meeting): bool
    {
        if ($meeting->status->isTerminal()) {
            return false;
        }
        return $user->hasPermissionTo('meeting.cancel')
            && (
                $meeting->creator_user_id === $user->id
                || $user->employee_id === $meeting->chairperson_employee_id
                || $user->hasRole('admin')
            );
    }

    public function transition(User $user, Meeting $meeting): bool
    {
        return $user->hasPermissionTo('meeting.update')
            && (
                $meeting->creator_user_id === $user->id
                || $user->employee_id === $meeting->chairperson_employee_id
                || $user->employee_id === $meeting->secretary_employee_id
                || $user->hasRole('admin')
            );
    }

    public function recordAttendance(User $user, Meeting $meeting): bool
    {
        return $user->hasPermissionTo('meeting.record_attendance')
            && (
                $user->employee_id === $meeting->secretary_employee_id
                || $user->employee_id === $meeting->chairperson_employee_id
                || $user->hasRole('admin')
            );
    }
}
