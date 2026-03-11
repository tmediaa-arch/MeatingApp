<?php

declare(strict_types=1);

namespace App\Domains\Tasks\Actions;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Services\NotificationDispatcher;
use App\Domains\Tasks\Enums\TaskStatus;
use App\Domains\Tasks\Models\Task;
use Illuminate\Support\Facades\DB;

class CompleteTaskAction
{
    public function __construct(
        private readonly AuditService $auditService,
        private readonly TransitionTaskStatusAction $transitionAction,
        private readonly NotificationDispatcher $dispatcher,
    ) {
    }

    public function execute(
        Task $task,
        User $approver,
        string $completionQuality = 'good',
        ?string $approverComment = null,
    ): Task {
        return DB::transaction(function () use ($task, $approver, $completionQuality, $approverComment) {
            $task->update([
                'completion_quality' => $completionQuality,
            ]);

            $this->transitionAction->execute(
                task: $task,
                newStatus: TaskStatus::Completed,
                reason: $approverComment ?? ('تأیید توسط ' . $approver->name),
            );

            // اطلاع به assignee
            if ($task->assignee_user_id) {
                $this->dispatcher->send(
                    templateKey: 'task.completed',
                    recipient: $task->assignee_user_id,
                    variables: [
                        'task_number' => $task->task_number,
                        'task_title' => $task->title,
                        'quality' => $completionQuality,
                    ],
                    notifiable: $task,
                );
            }

            $this->auditService->log(
                event: 'task_completed',
                auditable: $task,
                description: sprintf(
                    "وظیفه '%s' با کیفیت '%s' تأیید و تکمیل شد",
                    $task->task_number,
                    $completionQuality,
                ),
                context: ['quality' => $completionQuality],
                severity: 'notice',
            );

            return $task->fresh();
        });
    }
}
