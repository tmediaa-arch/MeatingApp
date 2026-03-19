<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Services\Engine\Handlers;

use App\Domains\Workflow\Enums\UserTaskStatus;
use App\Domains\Workflow\Models\ProcessElement;
use App\Domains\Workflow\Models\ProcessInstance;
use App\Domains\Workflow\Models\ProcessToken;
use App\Domains\Workflow\Models\UserTask;
use App\Domains\Workflow\Services\Engine\ExpressionEvaluator;

/**
 * UserTaskHandler:
 *
 * 1. UserTask جدید ایجاد می‌کند (با assignee و form schema)
 * 2. token را در حالت waiting قرار می‌دهد
 * 3. وقتی UserTask توسط Action تکمیل شد، CompleteUserTaskAction
 *    token را activate می‌کند و موتور به مرحله بعد می‌رود.
 */
class UserTaskHandler implements ElementHandlerInterface
{
    public function __construct(private readonly ExpressionEvaluator $evaluator)
    {
    }

    public function execute(
        ProcessInstance $instance,
        ProcessToken $token,
        ProcessElement $element,
        array $variables,
    ): ElementHandlerResult {
        // resolve assignee
        $assigneeUserId = null;
        $candidateUsers = [];
        $candidateGroups = [];

        if ($expr = $element->getAssigneeExpression()) {
            $resolved = $this->evaluator->resolve($expr, $variables);
            $assigneeUserId = is_numeric($resolved) ? (int) $resolved : null;
        }

        if ($expr = $element->getCandidateUsersExpression()) {
            $resolved = $this->evaluator->resolve($expr, $variables);
            $candidateUsers = is_array($resolved)
                ? array_map('intval', $resolved)
                : array_map('intval', array_map('trim', explode(',', (string) $resolved)));
        }

        if ($expr = $element->getCandidateRolesExpression()) {
            $resolved = $this->evaluator->resolve($expr, $variables);
            $candidateGroups = is_array($resolved)
                ? $resolved
                : array_map('trim', explode(',', (string) $resolved));
        }

        // resolve due_at
        $dueAt = null;
        if ($expr = $element->getDueDateExpression()) {
            $resolved = $this->evaluator->resolve($expr, $variables);
            if (is_numeric($resolved)) {
                $dueAt = \Carbon\Carbon::createFromTimestamp($resolved);
            } elseif (is_string($resolved)) {
                try {
                    $dueAt = \Carbon\Carbon::parse($resolved);
                } catch (\Throwable) {
                    // ignore parse errors
                }
            }
        }

        $priority = $element->properties['priority'] ?? 'normal';

        UserTask::create([
            'instance_id' => $instance->id,
            'token_id' => $token->id,
            'element_id' => $element->element_id,
            'name' => $element->name ?? $element->element_id,
            'description' => $element->properties['documentation'] ?? null,
            'assignee_user_id' => $assigneeUserId,
            'candidate_user_ids' => $candidateUsers,
            'candidate_role_names' => $candidateGroups,
            'status' => $assigneeUserId ? UserTaskStatus::Assigned : UserTaskStatus::Created,
            'priority' => $priority,
            'due_at' => $dueAt,
            'form_schema' => $element->form_schema,
        ]);

        return ElementHandlerResult::wait();
    }
}
