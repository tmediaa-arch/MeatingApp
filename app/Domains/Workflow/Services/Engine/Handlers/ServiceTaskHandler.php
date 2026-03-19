<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Services\Engine\Handlers;

use App\Domains\Workflow\Exceptions\WorkflowException;
use App\Domains\Workflow\Models\ProcessElement;
use App\Domains\Workflow\Models\ProcessInstance;
use App\Domains\Workflow\Models\ProcessToken;
use App\Domains\Workflow\Services\Engine\ExpressionEvaluator;
use App\Domains\Workflow\Services\ServiceTasks\ServiceTaskRegistry;
use Illuminate\Support\Facades\Log;

/**
 * ServiceTaskHandler:
 *  1. کلید service task را از element.service_task_class می‌خواند
 *  2. در whitelist بررسی می‌کند
 *  3. اجرا می‌کند و متغیرهای بازگشتی را به instance اضافه می‌کند
 *  4. token را به outgoing flows هدایت می‌کند
 *
 * در صورت خطا، یک Incident ایجاد می‌شود و token در waiting قرار می‌گیرد.
 */
class ServiceTaskHandler implements ElementHandlerInterface
{
    public function __construct(
        private readonly ServiceTaskRegistry $registry,
        private readonly ExpressionEvaluator $evaluator,
    ) {
    }

    public function execute(
        ProcessInstance $instance,
        ProcessToken $token,
        ProcessElement $element,
        array $variables,
    ): ElementHandlerResult {
        $key = $element->service_task_class;
        if (!$key) {
            throw WorkflowException::bpmnParseError(
                "ServiceTask '{$element->element_id}' بدون mms:serviceTaskClass تعریف شده.",
            );
        }

        $task = $this->registry->get($key);

        // resolve config — هر مقدار ممکن است یک expression باشد
        $rawConfig = $element->service_task_config ?? [];
        $resolvedConfig = [];
        foreach ($rawConfig as $k => $v) {
            $resolvedConfig[$k] = is_string($v) ? $this->evaluator->resolve($v, $variables) : $v;
        }

        try {
            $newVars = $task->execute($instance, $token, $resolvedConfig, $variables);
        } catch (\Throwable $e) {
            Log::error("ServiceTask '{$key}' failed", [
                'instance' => $instance->instance_uuid,
                'element' => $element->element_id,
                'error' => $e->getMessage(),
            ]);
            throw WorkflowException::expressionEvaluationFailed(
                "ServiceTask {$key}",
                $e->getMessage(),
            );
        }

        return ElementHandlerResult::continueTo(
            flowIds: $element->getOutgoingFlows(),
            variables: $newVars,
        );
    }
}
