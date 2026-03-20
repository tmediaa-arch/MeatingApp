<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Actions;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Identity\Models\User;
use App\Domains\Workflow\Enums\UserTaskStatus;
use App\Domains\Workflow\Exceptions\WorkflowException;
use App\Domains\Workflow\Models\ProcessHistory;
use App\Domains\Workflow\Models\UserTask;
use App\Domains\Workflow\Services\Engine\VariablesService;
use App\Domains\Workflow\Services\Runtime\WorkflowEngine;
use Illuminate\Support\Facades\DB;

/**
 * تکمیل یک UserTask توسط کاربر مجاز.
 *
 * مراحل:
 *  1. اعتبارسنجی مجوز
 *  2. ثبت form_data و outcome
 *  3. تغییر وضعیت به Completed
 *  4. merge کردن form_data به variables instance
 *  5. wake up token و ادامه اجرا
 */
class CompleteUserTaskAction
{
    public function __construct(
        private readonly WorkflowEngine $engine,
        private readonly VariablesService $variables,
        private readonly AuditService $auditService,
    ) {
    }

    /**
     * @param UserTask $userTask
     * @param User $completer
     * @param array $formData
     * @param string|null $outcome
     * @param string|null $comment
     */
    public function execute(
        UserTask $userTask,
        User $completer,
        array $formData = [],
        ?string $outcome = null,
        ?string $comment = null,
    ): UserTask {
        if ($userTask->status === UserTaskStatus::Completed) {
            throw WorkflowException::userTaskAlreadyCompleted();
        }

        if (!$userTask->canBeCompletedBy($completer)) {
            throw WorkflowException::notAuthorizedForUserTask();
        }

        return DB::transaction(function () use ($userTask, $completer, $formData, $outcome, $comment) {
            $userTask->update([
                'status' => UserTaskStatus::Completed,
                'form_data' => $formData,
                'outcome' => $outcome,
                'outcome_comment' => $comment,
                'completed_at' => now(),
                'completed_by_user_id' => $completer->id,
            ]);

            // merge کردن form_data به variables
            $varsToSet = $formData;
            if ($outcome !== null) {
                $varsToSet['_last_user_task_outcome'] = $outcome;
            }
            if (!empty($varsToSet)) {
                $this->variables->setMany($userTask->instance, $varsToSet);
            }

            ProcessHistory::log(
                instanceId: $userTask->instance_id,
                tokenId: $userTask->token_id,
                eventType: 'user_task_completed',
                elementId: $userTask->element_id,
                elementType: 'userTask',
                elementName: $userTask->name,
                payload: [
                    'outcome' => $outcome,
                    'form_data_keys' => array_keys($formData),
                ],
                actorUserId: $completer->id,
            );

            $this->auditService->log(
                event: 'user_task_completed',
                auditable: $userTask,
                description: "UserTask '{$userTask->name}' توسط {$completer->name} تکمیل شد",
                context: ['outcome' => $outcome, 'instance_uuid' => $userTask->instance->instance_uuid],
                severity: 'info',
            );

            // ادامه اجرا
            $this->engine->wakeUpToken($userTask->token);

            return $userTask->fresh();
        });
    }
}
