<?php

declare(strict_types=1);

namespace App\Domains\Tasks\Actions;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Organization\Models\Organization;
use App\Domains\Tasks\Enums\TaskPriority;
use App\Domains\Tasks\Enums\TaskStatus;
use App\Domains\Tasks\Enums\TaskType;
use App\Domains\Tasks\Exceptions\TaskException;
use App\Domains\Tasks\Models\Task;
use Illuminate\Support\Facades\DB;

class CreateTaskAction
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {
    }

    public function execute(array $data): Task
    {
        // اعتبارسنجی due_date
        if (isset($data['due_date'])) {
            $dueDate = $data['due_date'] instanceof \DateTimeInterface
                ? $data['due_date']
                : new \DateTimeImmutable($data['due_date']);

            if ($dueDate < new \DateTimeImmutable('today')) {
                throw TaskException::dueDateInPast();
            }
            $data['due_date'] = $dueDate;
        }

        return DB::transaction(function () use ($data) {
            $taskNumber = $this->generateTaskNumber($data['organization_id']);

            $task = Task::create([
                'organization_id' => $data['organization_id'],
                'task_number' => $taskNumber,
                'resolution_id' => $data['resolution_id'] ?? null,
                'meeting_id' => $data['meeting_id'] ?? null,
                'parent_task_id' => $data['parent_task_id'] ?? null,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'type' => $data['type'] ?? TaskType::Action,
                'priority' => $data['priority'] ?? TaskPriority::Normal,
                'status' => isset($data['assignee_employee_id']) || isset($data['assignee_user_id'])
                    ? TaskStatus::Assigned
                    : TaskStatus::Open,
                'assignee_employee_id' => $data['assignee_employee_id'] ?? null,
                'assignee_user_id' => $data['assignee_user_id'] ?? null,
                'assignee_org_unit_id' => $data['assignee_org_unit_id'] ?? null,
                'supervisor_employee_id' => $data['supervisor_employee_id'] ?? null,
                'approver_employee_id' => $data['approver_employee_id'] ?? null,
                'assigned_at' => (isset($data['assignee_employee_id']) || isset($data['assignee_user_id']))
                    ? now()
                    : null,
                'due_date' => $data['due_date'] ?? null,
                'estimated_hours' => $data['estimated_hours'] ?? null,
                'progress_percent' => 0,
                'confidentiality_level' => $data['confidentiality_level'] ?? 'internal',
                'tags' => $data['tags'] ?? [],
                'metadata' => $data['metadata'] ?? [],
                'creator_user_id' => $data['creator_user_id'] ?? auth()->id(),
            ]);

            $this->auditService->log(
                event: 'task_created',
                auditable: $task,
                description: sprintf(
                    "وظیفه '%s' ایجاد شد: %s",
                    $task->task_number,
                    $task->title,
                ),
                context: [
                    'assignee_id' => $task->assignee_employee_id,
                    'due_date' => $task->due_date?->format('Y-m-d'),
                ],
                severity: 'info',
            );

            return $task;
        });
    }

    private function generateTaskNumber(int $organizationId): string
    {
        $orgCode = Organization::find($organizationId)->code ?? 'ORG';
        $year = now()->year;
        $prefix = "{$orgCode}-TSK-{$year}-";

        $last = Task::where('organization_id', $organizationId)
            ->where('task_number', 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->value('task_number');

        $nextNum = $last
            ? ((int) substr($last, strrpos($last, '-') + 1)) + 1
            : 1;

        return sprintf('%s%04d', $prefix, $nextNum);
    }
}
