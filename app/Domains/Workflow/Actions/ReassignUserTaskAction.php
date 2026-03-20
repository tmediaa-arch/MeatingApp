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

/**
 * انتقال UserTask به فرد دیگر.
 *
 * این یک عمل administrative است که می‌تواند بدون رضایت assignee فعلی انجام شود.
 *
 * نکته: status فعلی به Reassigned تغییر می‌کند و یک کپی جدید برای assignee
 * جدید با status=Assigned ایجاد می‌شود. token همان token قبلی است.
 */
class ReassignUserTaskAction
{
    public function __construct(private readonly AuditService $auditService)
    {
    }

    public function execute(
        UserTask $userTask,
        User $newAssignee,
        User $actor,
        ?string $reason = null,
    ): UserTask {
        if (!$userTask->status->isOpen()) {
            throw WorkflowException::userTaskNotAssignable();
        }

        if ($userTask->assignee_user_id === $newAssignee->id) {
            return $userTask;
        }

        return DB::transaction(function () use ($userTask, $newAssignee, $actor, $reason) {
            $previousAssignee = $userTask->assignee_user_id;

            // UserTask قبلی → Reassigned
            $userTask->update(['status' => UserTaskStatus::Reassigned]);

            // UserTask جدید با همان token
            $new = UserTask::create([
                'instance_id' => $userTask->instance_id,
                'token_id' => $userTask->token_id,
                'element_id' => $userTask->element_id,
                'name' => $userTask->name,
                'description' => $userTask->description,
                'assignee_user_id' => $newAssignee->id,
                'candidate_user_ids' => $userTask->candidate_user_ids,
                'candidate_role_names' => $userTask->candidate_role_names,
                'status' => UserTaskStatus::Assigned,
                'priority' => $userTask->priority,
                'due_at' => $userTask->due_at,
                'follow_up_at' => $userTask->follow_up_at,
                'form_schema' => $userTask->form_schema,
            ]);

            ProcessHistory::log(
                instanceId: $userTask->instance_id,
                tokenId: $userTask->token_id,
                eventType: 'user_task_reassigned',
                elementId: $userTask->element_id,
                elementType: 'userTask',
                payload: [
                    'from_user_id' => $previousAssignee,
                    'to_user_id' => $newAssignee->id,
                    'reason' => $reason,
                ],
                actorUserId: $actor->id,
            );

            $this->auditService->log(
                event: 'user_task_reassigned',
                auditable: $new,
                description: "UserTask '{$userTask->name}' به {$newAssignee->name} انتقال یافت",
                context: ['from' => $previousAssignee, 'reason' => $reason],
                severity: 'notice',
            );

            return $new;
        });
    }
}
