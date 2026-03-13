<?php

declare(strict_types=1);

namespace App\Domains\ServiceRequests\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\ServiceRequests\Enums\ServiceRequestStatus;
use App\Domains\ServiceRequests\Models\ServiceRequest;

class CompleteServiceRequestAction
{
    public function __construct(private readonly TransitionServiceRequestStatusAction $transition)
    {
    }

    public function execute(
        ServiceRequest $request,
        User $actor,
        ?float $actualCost = null,
        ?string $comment = null,
    ): ServiceRequest {
        if ($actualCost !== null) {
            $request->update(['actual_cost' => $actualCost]);
        }

        return $this->transition->execute($request, ServiceRequestStatus::Completed, $actor, $comment);
    }
}
