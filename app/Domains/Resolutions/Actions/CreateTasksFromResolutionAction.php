<?php

declare(strict_types=1);

namespace App\Domains\Resolutions\Actions;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Notifications\Services\NotificationDispatcher;
use App\Domains\Resolutions\Enums\AssigneeRole;
use App\Domains\Resolutions\Models\Resolution;
use App\Domains\Tasks\Actions\CreateTaskAction;
use App\Domains\Tasks\Enums\TaskPriority;
use App\Domains\Tasks\Enums\TaskType;
use App\Domains\Tasks\Models\Task;
use Illuminate\Support\Facades\DB;

/**
 * بعد از Approve شدن یک مصوبه، برای هر Executor یک Task ایجاد می‌کند.
 *
 * این Action یا توسط Observer (در فاز ۴ کامل می‌شود)
 * یا به‌صورت دستی توسط Action دیگر فراخوانی می‌شود.
 */
class CreateTasksFromResolutionAction
{
    public function __construct(
        private readonly AuditService $auditService,
        private readonly CreateTaskAction $createTaskAction,
        private readonly NotificationDispatcher $dispatcher,
    ) {
    }

    /**
     * @return Task[] لیست taskهای ایجاد شده
     */
    public function execute(Resolution $resolution): array
    {
        $executors = $resolution->assignees()
            ->where('role', AssigneeRole::Executor->value)
            ->get();

        if ($executors->isEmpty()) {
            return [];
        }

        $supervisors = $resolution->assignees()
            ->where('role', AssigneeRole::Supervisor->value)
            ->get();

        return DB::transaction(function () use ($resolution, $executors, $supervisors) {
            $tasks = [];

            foreach ($executors as $executor) {
                $task = $this->createTaskAction->execute([
                    'organization_id' => $resolution->organization_id,
                    'resolution_id' => $resolution->id,
                    'meeting_id' => $resolution->meeting_id,
                    'title' => sprintf('اجرای مصوبه: %s', $resolution->title),
                    'description' => strip_tags($resolution->content),
                    'type' => TaskType::Action,
                    'priority' => $this->mapResolutionPriority($resolution->priority),
                    'assignee_employee_id' => $executor->employee_id,
                    'assignee_org_unit_id' => $executor->org_unit_id,
                    'supervisor_employee_id' => $supervisors->first()?->employee_id,
                    'due_date' => $resolution->due_date,
                    'confidentiality_level' => $resolution->minute->confidentiality_level,
                    'creator_user_id' => $resolution->creator_user_id,
                    'metadata' => [
                        'auto_created_from_resolution' => true,
                        'resolution_number' => $resolution->resolution_number,
                    ],
                ]);

                $tasks[] = $task;

                // notify assignee
                if ($executor->employee?->user_id) {
                    $this->dispatcher->send(
                        templateKey: 'task.assigned',
                        recipient: $executor->employee->user_id,
                        variables: [
                            'task_number' => $task->task_number,
                            'task_title' => $task->title,
                            'resolution_number' => $resolution->resolution_number,
                            'due_date' => $task->due_date?->format('Y/m/d') ?? '—',
                        ],
                        notifiable: $task,
                    );
                }
            }

            $this->auditService->log(
                event: 'tasks_created_from_resolution',
                auditable: $resolution,
                description: sprintf(
                    "%d وظیفه از مصوبه '%s' ایجاد شد",
                    count($tasks),
                    $resolution->resolution_number,
                ),
                context: [
                    'task_count' => count($tasks),
                    'task_numbers' => array_map(fn ($t) => $t->task_number, $tasks),
                ],
                severity: 'notice',
            );

            return $tasks;
        });
    }

    private function mapResolutionPriority(string $priority): TaskPriority
    {
        return match ($priority) {
            'critical' => TaskPriority::Critical,
            'high' => TaskPriority::High,
            'low' => TaskPriority::Low,
            default => TaskPriority::Normal,
        };
    }
}
