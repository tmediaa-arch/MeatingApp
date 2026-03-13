<?php

declare(strict_types=1);

namespace App\Domains\ServiceRequests\Actions;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Identity\Models\User;
use App\Domains\Organization\Models\Employee;
use App\Domains\ServiceRequests\Models\ServiceRequest;
use App\Domains\ServiceRequests\Models\ServiceRequestUpdate;
use Illuminate\Support\Facades\DB;

/**
 * انتساب درخواست به یک کارمند مسئول.
 */
class AssignServiceRequestAction
{
    public function __construct(private readonly AuditService $auditService)
    {
    }

    public function execute(
        ServiceRequest $request,
        Employee $assignee,
        User $actor,
        ?string $comment = null,
    ): ServiceRequest {
        return DB::transaction(function () use ($request, $assignee, $actor, $comment) {
            $previous = $request->assigned_to_employee_id;

            $request->update(['assigned_to_employee_id' => $assignee->id]);

            ServiceRequestUpdate::create([
                'service_request_id' => $request->id,
                'update_type' => 'assignment_change',
                'from_value' => $previous ? (string) $previous : null,
                'to_value' => (string) $assignee->id,
                'comment' => $comment,
                'actor_user_id' => $actor->id,
            ]);

            $this->auditService->log(
                event: 'service_request_assigned',
                auditable: $request,
                description: sprintf(
                    "درخواست '%s' به %s ارجاع شد",
                    $request->request_number,
                    $assignee->full_name,
                ),
                severity: 'info',
            );

            return $request->fresh();
        });
    }
}
