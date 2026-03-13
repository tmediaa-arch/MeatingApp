<?php

declare(strict_types=1);

namespace App\Domains\ServiceRequests\Observers;

use App\Domains\ServiceRequests\Enums\ServiceRequestStatus;
use App\Domains\ServiceRequests\Exceptions\ServiceRequestException;
use App\Domains\ServiceRequests\Models\ServiceRequest;

class ServiceRequestObserver
{
    public function updating(ServiceRequest $request): void
    {
        if (!$request->isDirty('status')) return;

        $old = $request->getOriginal('status');
        $new = $request->status;

        $oldEnum = $old instanceof ServiceRequestStatus
            ? $old
            : ServiceRequestStatus::tryFrom((string) $old);
        $newEnum = $new instanceof ServiceRequestStatus
            ? $new
            : ServiceRequestStatus::tryFrom((string) $new);

        if ($oldEnum && $newEnum && !$oldEnum->canTransitionTo($newEnum)) {
            throw ServiceRequestException::invalidTransition($oldEnum, $newEnum);
        }
    }
}
