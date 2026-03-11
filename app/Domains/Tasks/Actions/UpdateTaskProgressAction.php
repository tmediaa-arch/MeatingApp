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

class UpdateTaskProgressAction
{
    public function __construct(
        private readonly AuditService $auditService,
        private readonly TransitionTaskStatusAction $transitionAction,
    ) {
    }

    public function execute(Task $task, int $progressPercent, ?string $comment = null): Task
    {
        if (!$task->status->isOpen()) {
            throw TaskException::cannotUpdateTerminal($task->status);
        }

        $progressPercent = max(0, min(100, $progressPercent));

        return DB::transaction(function () use ($task, $progressPercent, $comment) {
            $oldProgress = $task->progress_percent;

            if ($oldProgress === $progressPercent && !$comment) {
                return $task; // no-op
            }

            $task->update(['progress_percent' => $progressPercent]);

            // اگر شروع به کار کرده، status را InProgress کن
            if ($task->status === TaskStatus::Assigned && $progressPercent > 0) {
                $this->transitionAction->execute(
                    task: $task,
                    newStatus: TaskStatus::InProgress,
                    reason: 'پیشرفت کار آغاز شد',
                );
            }

            TaskUpdate::create([
                'task_id' => $task->id,
                'updater_user_id' => auth()->id() ?? 1,
                'update_type' => TaskUpdateType::ProgressUpdate->value,
                'content' => $comment,
                'old_progress' => $oldProgress,
                'new_progress' => $progressPercent,
                'occurred_at' => now(),
            ]);

            $this->auditService->log(
                event: 'task_progress_updated',
                auditable: $task,
                description: sprintf(
                    "پیشرفت وظیفه '%s' از %d%% به %d%% به‌روز شد",
                    $task->task_number,
                    $oldProgress,
                    $progressPercent,
                ),
                context: [
                    'old_progress' => $oldProgress,
                    'new_progress' => $progressPercent,
                ],
                severity: 'info',
            );

            return $task->fresh();
        });
    }
}
