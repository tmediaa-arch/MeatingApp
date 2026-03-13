<?php

declare(strict_types=1);

namespace App\Domains\ServiceRequests\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\ServiceRequests\Enums\ServiceRequestStatus;
use App\Domains\ServiceRequests\Models\ServiceRequest;

/**
 * Wrappers ساده برای transitions رایج.
 */
class SubmitServiceRequestAction
{
    public function __construct(private readonly TransitionServiceRequestStatusAction $transition)
    {
    }

    public function execute(ServiceRequest $request, User $actor): ServiceRequest
    {
        return $this->transition->execute($request, ServiceRequestStatus::Submitted, $actor);
    }
}
