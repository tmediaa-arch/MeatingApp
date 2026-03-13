<?php

declare(strict_types=1);

namespace App\Domains\ServiceRequests\Actions;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Identity\Models\User;
use App\Domains\ServiceRequests\Enums\ServiceRequestStatus;
use App\Domains\ServiceRequests\Exceptions\ServiceRequestException;
use App\Domains\ServiceRequests\Models\ServiceRequest;
use App\Domains\ServiceRequests\Models\ServiceRequestUpdate;
use Illuminate\Support\Facades\DB;

/**
 * تغییر وضعیت یک ServiceRequest با اعتبارسنجی state machine.
 */
class TransitionServiceRequestStatusAction
{
    public function __construct(private readonly AuditService $auditService)
    {
    }

    public function execute(
        ServiceRequest $request,
        ServiceRequestStatus $newStatus,
        User $actor,
        ?string $comment = null,
    ): ServiceRequest {
        if ($request->status === $newStatus) {
            return $request; // idempotent
        }

        if (!$request->status->canTransitionTo($newStatus)) {
            throw ServiceRequestException::invalidTransition($request->status, $newStatus);
        }

        return DB::transaction(function () use ($request, $newStatus, $actor, $comment) {
            $oldStatus = $request->status;

            $updates = ['status' => $newStatus];

            // مدیریت timestamp های اختصاصی
            match ($newStatus) {
                ServiceRequestStatus::Submitted => $updates['submitted_at'] = $request->submitted_at ?? now(),
                ServiceRequestStatus::Approved, ServiceRequestStatus::Rejected => $updates = array_merge($updates, [
                    'reviewer_user_id' => $actor->id,
                    'reviewed_at' => now(),
                    'review_comment' => $comment,
                ]),
                ServiceRequestStatus::Completed => $updates['completed_at'] = now(),
                ServiceRequestStatus::Cancelled => $updates['cancelled_at'] = now(),
                default => null,
            };

            $request->update($updates);

            ServiceRequestUpdate::create([
                'service_request_id' => $request->id,
                'update_type' => 'status_change',
                'from_value' => $oldStatus->value,
                'to_value' => $newStatus->value,
                'comment' => $comment,
                'actor_user_id' => $actor->id,
            ]);

            $this->auditService->log(
                event: 'service_request_status_changed',
                auditable: $request,
                description: sprintf(
                    "وضعیت درخواست '%s' از '%s' به '%s' تغییر کرد",
                    $request->request_number,
                    $oldStatus->label(),
                    $newStatus->label(),
                ),
                context: ['from' => $oldStatus->value, 'to' => $newStatus->value, 'comment' => $comment],
                severity: 'info',
            );

            return $request->fresh();
        });
    }
}
