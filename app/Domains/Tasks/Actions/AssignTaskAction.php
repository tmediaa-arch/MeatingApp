<?php

declare(strict_types=1);

namespace App\Domains\Tasks\Actions;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Notifications\Services\NotificationDispatcher;
use App\Domains\Organization\Models\Employee;
use App\Domains\Tasks\Enums\TaskStatus;
use App\Domains\Tasks\Enums\TaskUpdateType;
use App\Domains\Tasks\Exceptions\TaskException;
use App\Domains\Tasks\Models\Task;
use App\Domains\Tasks\Models\TaskUpdate;
use Illuminate\Support\Facades\DB;

class AssignTaskAction
{
    public function __construct(
        private readonly AuditService $auditService,
        private readonly NotificationDispatcher $dispatcher,
        private readonly TransitionTaskStatusAction $transitionAction,
    ) {
    }

    public function execute(Task $task, Employee $assignee, ?Employee $supervisor = null): Task
    {
        if (!$task->status->isOpen()) {
            throw TaskException::cannotUpdateTerminal($task->status);
        }

        return DB::transaction(function () use ($task, $assignee, $supervisor) {
            $previousAssigneeId = $task->assignee_employee_id;

            $task->update([
                'assignee_employee_id' => $assignee->id,
                'assignee_user_id' => $assignee->user_id,
                'supervisor_employee_id' => $supervisor?->id ?? $task->supervisor_employee_id,
                'assigned_at' => now(),
            ]);

            // اگر هنوز Open بود، به Assigned منتقل کن
            if ($task->status === TaskStatus::Open) {
                $this->transitionAction->execute($task, TaskStatus::Assigned, 'ارجاع شد');
            }

            // ثبت update — اگر reassignment باشد
            if ($previousAssigneeId && $previousAssigneeId !== $assignee->id) {
                TaskUpdate::create([
                    'task_id' => $task->id,
                    'updater_user_id' => auth()->id() ?? 1,
                    'update_type' => TaskUpdateType::Reassignment->value,
                    'content' => "ارجاع از employee #{$previousAssigneeId} به employee #{$assignee->id}",
                    'occurred_at' => now(),
                ]);
            }

            // notify
            if ($assignee->user_id) {
                $this->dispatcher->send(
                    templateKey: 'task.assigned',
                    recipient: $assignee->user_id,
                    variables: [
                        'task_number' => $task->task_number,
                        'task_title' => $task->title,
                        'due_date' => $task->due_date?->format('Y/m/d') ?? '—',
                    ],
                    notifiable: $task,
                );
            }

            $this->auditService->log(
                event: 'task_assigned',
                auditable: $task,
                description: sprintf(
                    "وظیفه '%s' به '%s' ارجاع شد",
                    $task->task_number,
                    $assignee->full_name,
                ),
                context: [
                    'assignee_id' => $assignee->id,
                    'previous_assignee_id' => $previousAssigneeId,
                ],
                severity: 'info',
            );

            return $task->fresh();
        });
    }
}
