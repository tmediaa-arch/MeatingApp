<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Services\ServiceTasks;

use App\Domains\Tasks\Actions\CreateTaskAction;
use App\Domains\Tasks\Enums\TaskPriority;
use App\Domains\Tasks\Enums\TaskType;
use App\Domains\Workflow\Models\ProcessInstance;
use App\Domains\Workflow\Models\ProcessToken;

/**
 * Service Task: ایجاد یک Task (Phase 3) از فرایند.
 *
 * این پل بین Workflow Engine و دامنه Tasks است.
 *
 * Config:
 *   title:         عنوان وظیفه
 *   description:   شرح
 *   assignee:      employee_id
 *   due_in_days:   چند روز از حالا
 *   priority:      low / normal / high / critical
 */
class CreateTaskServiceTask implements ServiceTaskInterface
{
    public function __construct(private readonly CreateTaskAction $createTaskAction)
    {
    }

    public static function key(): string
    {
        return 'create_task';
    }

    public static function description(): string
    {
        return 'ایجاد یک وظیفه (Task)';
    }

    public static function configSchema(): array
    {
        return [
            'title' => ['type' => 'string', 'required' => true, 'label' => 'عنوان'],
            'description' => ['type' => 'string', 'required' => false, 'label' => 'شرح'],
            'assignee' => ['type' => 'expression', 'required' => true, 'label' => 'مجری (employee_id)'],
            'due_in_days' => ['type' => 'integer', 'required' => false, 'label' => 'مهلت (روز)'],
            'priority' => [
                'type' => 'string',
                'required' => false,
                'label' => 'اولویت',
                'options' => ['low', 'normal', 'high', 'critical'],
            ],
        ];
    }

    public function execute(
        ProcessInstance $instance,
        ProcessToken $token,
        array $config,
        array $variables,
    ): array {
        $assigneeId = is_numeric($config['assignee'] ?? null)
            ? (int) $config['assignee']
            : (int) ($variables[$config['assignee']] ?? 0);

        if (!$assigneeId) {
            throw new \DomainException('assignee_employee_id معتبر نیست.');
        }

        $dueInDays = (int) ($config['due_in_days'] ?? 7);
        $priority = TaskPriority::tryFrom($config['priority'] ?? 'normal') ?? TaskPriority::Normal;

        $task = $this->createTaskAction->execute([
            'organization_id' => $instance->organization_id,
            'title' => $config['title'] ?? "وظیفه از فرایند {$instance->process_key}",
            'description' => $config['description'] ?? null,
            'type' => TaskType::Action,
            'priority' => $priority,
            'assignee_employee_id' => $assigneeId,
            'due_date' => now()->addDays($dueInDays),
            'creator_user_id' => $instance->starter_user_id,
            'metadata' => [
                'workflow_instance_uuid' => $instance->instance_uuid,
                'workflow_token_uuid' => $token->token_uuid,
                'workflow_element_id' => $token->current_element_id,
            ],
        ]);

        return [
            '_last_task_id' => $task->id,
            '_last_task_number' => $task->task_number,
        ];
    }
}
