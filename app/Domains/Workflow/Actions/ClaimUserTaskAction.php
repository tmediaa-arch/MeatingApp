<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Actions;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Identity\Models\User;
use App\Domains\Workflow\Enums\UserTaskStatus;
use App\Domains\Workflow\Exceptions\WorkflowException;
use App\Domains\Workflow\Models\ProcessHistory;
use App\Domains\Workflow\Models\UserTask;
use Illuminate\Support\Facades\DB;

class ClaimUserTaskAction
{
    public function __construct(private readonly AuditService $auditService)
    {
    }

    public function execute(UserTask $userTask, User $claimer): UserTask
    {
        if (!$userTask->canBeClaimedBy($claimer)) {
            throw WorkflowException::userTaskNotAssignable();
        }

        if ($userTask->status === UserTaskStatus::Claimed
            && $userTask->assignee_user_id === $claimer->id
        ) {
            return $userTask; // idempotent
        }

        return DB::transaction(function () use ($userTask, $claimer) {
            $userTask->update([
                'status' => UserTaskStatus::Claimed,
                'assignee_user_id' => $claimer->id,
                'claimed_at' => now(),
            ]);

            ProcessHistory::log(
                instanceId: $userTask->instance_id,
                tokenId: $userTask->token_id,
                eventType: 'user_task_claimed',
                elementId: $userTask->element_id,
                elementType: 'userTask',
                actorUserId: $claimer->id,
            );

            $this->auditService->log(
                event: 'user_task_claimed',
                auditable: $userTask,
                description: "UserTask '{$userTask->name}' توسط {$claimer->name} claim شد",
                severity: 'info',
            );

            return $userTask->fresh();
        });
    }
}
