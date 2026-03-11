<?php

declare(strict_types=1);

namespace App\Domains\Tasks\Observers;

use App\Domains\Tasks\Enums\TaskStatus;
use App\Domains\Tasks\Exceptions\TaskException;
use App\Domains\Tasks\Models\Task;

class TaskObserver
{
    public function updating(Task $task): void
    {
        // state machine guard
        if ($task->isDirty('status')) {
            $original = TaskStatus::tryFrom($task->getOriginal('status'));
            $new = $task->status;
            if ($original && $original !== $new && !$original->canTransitionTo($new)) {
                throw TaskException::invalidStateTransition($original, $new);
            }
        }

        // به‌روزرسانی خودکار is_overdue هر بار update
        if ($task->due_date && !$task->status->isTerminal()) {
            $shouldBeOverdue = $task->due_date->isPast();
            if ($task->is_overdue !== $shouldBeOverdue) {
                $task->is_overdue = $shouldBeOverdue;
            }
        }

        // اگر terminal شد، is_overdue را false کن
        if ($task->isDirty('status') && $task->status->isTerminal()) {
            $task->is_overdue = false;
        }
    }
}
