<?php

declare(strict_types=1);

namespace App\Domains\ServiceRequests\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\ServiceRequests\Enums\ServiceRequestStatus;
use App\Domains\ServiceRequests\Models\ServiceRequest;

class ServiceRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('service_request.view');
    }

    public function view(User $user, ServiceRequest $request): bool
    {
        if ($user->can('service_request.view_all')) return true;
        if (!$user->can('service_request.view')) return false;

        // درخواست‌کننده، assignee، یا کاربر در provider_unit
        if ($request->requester_user_id === $user->id) return true;
        if ($user->employee && $request->assigned_to_employee_id === $user->employee->id) return true;
        if ($user->employee && $request->provider_unit_id === $user->employee->org_unit_id) return true;

        return false;
    }

    public function create(User $user): bool
    {
        return $user->can('service_request.create');
    }

    public function update(User $user, ServiceRequest $request): bool
    {
        if ($request->status === ServiceRequestStatus::Draft && $request->requester_user_id === $user->id) {
            return true;
        }
        return $user->can('service_request.update_any');
    }

    public function submit(User $user, ServiceRequest $request): bool
    {
        return $request->requester_user_id === $user->id
            && $request->status === ServiceRequestStatus::Draft;
    }

    public function review(User $user, ServiceRequest $request): bool
    {
        return $user->can('service_request.review')
            && in_array($request->status, [ServiceRequestStatus::Submitted, ServiceRequestStatus::UnderReview], true);
    }

    public function assign(User $user, ServiceRequest $request): bool
    {
        return $user->can('service_request.assign');
    }

    public function complete(User $user, ServiceRequest $request): bool
    {
        return $user->can('service_request.complete')
            && $request->status === ServiceRequestStatus::InProgress;
    }

    public function cancel(User $user, ServiceRequest $request): bool
    {
        return ($request->requester_user_id === $user->id
            || $user->can('service_request.cancel_any'))
            && $request->status->isOpen();
    }

    public function delete(User $user, ServiceRequest $request): bool
    {
        return $user->can('service_request.delete')
            && $request->status === ServiceRequestStatus::Draft;
    }
}
