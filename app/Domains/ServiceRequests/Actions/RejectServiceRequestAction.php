<?php

declare(strict_types=1);

namespace App\Domains\ServiceRequests\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\ServiceRequests\Enums\ServiceRequestStatus;
use App\Domains\ServiceRequests\Models\ServiceRequest;

class RejectServiceRequestAction
{
    public function __construct(private readonly TransitionServiceRequestStatusAction $transition)
    {
    }

    public function execute(ServiceRequest $request, User $reviewer, string $reason): ServiceRequest
    {
        return $this->transition->execute($request, ServiceRequestStatus::Rejected, $reviewer, $reason);
    }
}
