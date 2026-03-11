<?php

declare(strict_types=1);

namespace App\Domains\Tasks\Actions;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Services\NotificationDispatcher;
use App\Domains\Tasks\Enums\TaskUpdateType;
use App\Domains\Tasks\Exceptions\TaskException;
use App\Domains\Tasks\Models\Task;
use App\Domains\Tasks\Models\TaskExtension;
use App\Domains\Tasks\Models\TaskUpdate;
use Illuminate\Support\Facades\DB;

class RequestExtensionAction
{
    public function __construct(
        private readonly AuditService $auditService,
        private readonly NotificationDispatcher $dispatcher,
    ) {
    }

    public function execute(
        Task $task,
        User $requester,
        \DateTimeInterface $newDueDate,
        string $reason,
    ): TaskExtension {
        if (!$task->due_date) {
            throw new \DomainException('وظیفه‌ای بدون مهلت قابل تمدید نیست.');
        }

        if ($newDueDate <= $task->due_date) {
            throw TaskException::extensionDateBeforeDue();
        }

        // فقط یک درخواست pending در هر زمان
        if ($task->extensions()->where('status', 'pending')->exists()) {
            throw TaskException::extensionAlreadyPending();
        }

        return DB::transaction(function () use ($task, $requester, $newDueDate, $reason) {
            $extension = TaskExtension::create([
                'task_id' => $task->id,
                'requested_by_user_id' => $requester->id,
                'original_due_date' => $task->due_date,
                'requested_due_date' => $newDueDate,
                'reason' => $reason,
                'status' => 'pending',
            ]);

            TaskUpdate::create([
                'task_id' => $task->id,
                'updater_user_id' => $requester->id,
                'update_type' => TaskUpdateType::Extension->value,
                'content' => "درخواست تمدید تا " . $newDueDate->format('Y-m-d') . "\nدلیل: " . $reason,
                'occurred_at' => now(),
            ]);

            // اطلاع به supervisor یا approver
            $approverUserId = $task->approver?->user_id ?? $task->supervisor?->user_id;
            if ($approverUserId) {
                $this->dispatcher->send(
                    templateKey: 'task.extension_requested',
                    recipient: $approverUserId,
                    variables: [
                        'task_number' => $task->task_number,
                        'requester_name' => $requester->name,
                        'new_due_date' => $newDueDate->format('Y/m/d'),
                        'reason' => $reason,
                    ],
                    notifiable: $task,
                );
            }

            $this->auditService->log(
                event: 'task_extension_requested',
                auditable: $task,
                description: sprintf(
                    "درخواست تمدید مهلت وظیفه '%s' تا %s",
                    $task->task_number,
                    $newDueDate->format('Y-m-d'),
                ),
                severity: 'info',
            );

            return $extension;
        });
    }
}
