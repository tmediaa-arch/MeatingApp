<?php

declare(strict_types=1);

namespace App\Domains\Tasks\Actions;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Tasks\Enums\TaskStatus;
use App\Domains\Tasks\Enums\TaskUpdateType;
use App\Domains\Tasks\Exceptions\TaskException;
use App\Domains\Tasks\Models\Task;
use App\Domains\Tasks\Models\TaskUpdate;
use Illuminate\Support\Facades\DB;

class TransitionTaskStatusAction
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {
    }

    public function execute(
        Task $task,
        TaskStatus $newStatus,
        ?string $reason = null,
    ): Task {
        if ($task->status === $newStatus) {
            return $task;
        }

        if (!$task->status->canTransitionTo($newStatus)) {
            throw TaskException::invalidStateTransition($task->status, $newStatus);
        }

        return DB::transaction(function () use ($task, $newStatus, $reason) {
            $oldStatus = $task->status;

            $updates = ['status' => $newStatus];

            // تنظیم timestamp بر اساس وضعیت
            if ($newStatus === TaskStatus::InProgress && !$task->started_at) {
                $updates['started_at'] = now();
            } elseif ($newStatus === TaskStatus::Submitted) {
                $updates['submitted_at'] = now();
            } elseif ($newStatus === TaskStatus::Completed) {
                $updates['completed_at'] = now();
                $updates['progress_percent'] = 100;
            } elseif ($newStatus === TaskStatus::Cancelled) {
                $updates['cancelled_at'] = now();
            }

            $task->update($updates);

            // ثبت TaskUpdate
            TaskUpdate::create([
                'task_id' => $task->id,
                'updater_user_id' => auth()->id() ?? 1,
                'update_type' => TaskUpdateType::StatusChange->value,
                'content' => $reason,
                'old_status' => $oldStatus->value,
                'new_status' => $newStatus->value,
                'occurred_at' => now(),
            ]);

            $this->auditService->log(
                event: 'task_status_changed',
                auditable: $task,
                description: sprintf(
                    'وضعیت وظیفه "%s" از "%s" به "%s" تغییر کرد',
                    $task->task_number,
                    $oldStatus->label(),
                    $newStatus->label(),
                ),
                oldValues: ['status' => $oldStatus->value],
                newValues: ['status' => $newStatus->value],
                context: ['reason' => $reason],
                severity: 'info',
            );

            return $task->fresh();
        });
    }
}
