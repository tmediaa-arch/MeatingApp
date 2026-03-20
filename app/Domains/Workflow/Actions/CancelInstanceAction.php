<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Actions;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Identity\Models\User;
use App\Domains\Workflow\Enums\ProcessInstanceStatus;
use App\Domains\Workflow\Enums\TokenStatus;
use App\Domains\Workflow\Enums\UserTaskStatus;
use App\Domains\Workflow\Exceptions\WorkflowException;
use App\Domains\Workflow\Models\ProcessHistory;
use App\Domains\Workflow\Models\ProcessInstance;
use Illuminate\Support\Facades\DB;

class CancelInstanceAction
{
    public function __construct(private readonly AuditService $auditService)
    {
    }

    public function execute(
        ProcessInstance $instance,
        User $actor,
        ?string $reason = null,
    ): ProcessInstance {
        if (!$instance->status->canTransitionTo(ProcessInstanceStatus::Cancelled)) {
            throw WorkflowException::invalidTransition(
                $instance->status->value,
                ProcessInstanceStatus::Cancelled->value,
            );
        }

        return DB::transaction(function () use ($instance, $actor, $reason) {
            // لغو همه tokenهای زنده
            $instance->tokens()
                ->whereIn('status', [TokenStatus::Active->value, TokenStatus::Waiting->value])
                ->update([
                    'status' => TokenStatus::Cancelled->value,
                    'exited_at' => now(),
                ]);

            // لغو UserTaskهای باز
            $instance->userTasks()
                ->whereIn('status', [
                    UserTaskStatus::Created->value,
                    UserTaskStatus::Assigned->value,
                    UserTaskStatus::Claimed->value,
                ])
                ->update(['status' => UserTaskStatus::Cancelled->value]);

            $instance->update([
                'status' => ProcessInstanceStatus::Cancelled,
                'cancelled_at' => now(),
                'end_reason' => $reason,
            ]);

            ProcessHistory::log(
                instanceId: $instance->id,
                tokenId: null,
                eventType: 'instance_cancelled',
                payload: ['reason' => $reason],
                actorUserId: $actor->id,
            );

            $this->auditService->log(
                event: 'process_instance_cancelled',
                auditable: $instance,
                description: "instance '{$instance->instance_uuid}' لغو شد",
                context: ['reason' => $reason],
                severity: 'warning',
            );

            return $instance->fresh();
        });
    }
}
