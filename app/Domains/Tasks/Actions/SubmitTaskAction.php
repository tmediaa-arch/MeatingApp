<?php

declare(strict_types=1);

namespace App\Domains\Tasks\Actions;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Services\NotificationDispatcher;
use App\Domains\Tasks\Enums\TaskStatus;
use App\Domains\Tasks\Exceptions\TaskException;
use App\Domains\Tasks\Models\Task;
use Illuminate\Support\Facades\DB;

class SubmitTaskAction
{
    public function __construct(
        private readonly AuditService $auditService,
        private readonly TransitionTaskStatusAction $transitionAction,
        private readonly NotificationDispatcher $dispatcher,
    ) {
    }

    public function execute(Task $task, User $submitter, ?string $resultSummary = null): Task
    {
        if ($task->assignee_user_id !== $submitter->id
            && $task->assignee_employee_id !== $submitter->employee_id
            && !$submitter->hasRole('super-admin')
        ) {
            throw TaskException::notAssignee();
        }

        return DB::transaction(function () use ($task, $submitter, $resultSummary) {
            if ($resultSummary) {
                $task->update(['result_summary' => $resultSummary]);
            }

            // اگر approver دارد، به UnderReview، در غیر این صورت Submitted
            $newStatus = $task->approver_employee_id
                ? TaskStatus::UnderReview
                : TaskStatus::Submitted;

            $this->transitionAction->execute($task, $newStatus, 'ارسال شد توسط ' . $submitter->name);

            // اطلاع به approver
            if ($task->approver?->user_id) {
                $this->dispatcher->send(
                    templateKey: 'task.submitted',
                    recipient: $task->approver->user_id,
                    variables: [
                        'task_number' => $task->task_number,
                        'task_title' => $task->title,
                        'submitter_name' => $submitter->name,
                    ],
                    notifiable: $task,
                );
            }

            $this->auditService->log(
                event: 'task_submitted',
                auditable: $task,
                description: sprintf("وظیفه '%s' ارسال شد", $task->task_number),
                severity: 'info',
            );

            return $task->fresh();
        });
    }
}
