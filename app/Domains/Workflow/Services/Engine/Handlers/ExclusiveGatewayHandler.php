<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Services\Engine\Handlers;

use App\Domains\Workflow\Exceptions\WorkflowException;
use App\Domains\Workflow\Models\ProcessElement;
use App\Domains\Workflow\Models\ProcessInstance;
use App\Domains\Workflow\Models\ProcessToken;
use App\Domains\Workflow\Services\Engine\ExpressionEvaluator;
use App\Domains\Workflow\Services\Engine\FlowResolver;

/**
 * ExclusiveGateway (XOR):
 *  - دقیقاً یک مسیر خروجی انتخاب می‌شود
 *  - اولین flow با condition=true انتخاب می‌شود
 *  - اگر هیچکدام true نشدند، default انتخاب می‌شود
 *  - اگر default هم نباشد و همه false → exception
 */
class ExclusiveGatewayHandler implements ElementHandlerInterface
{
    public function __construct(
        private readonly ExpressionEvaluator $evaluator,
        private readonly FlowResolver $flowResolver,
    ) {
    }

    public function execute(
        ProcessInstance $instance,
        ProcessToken $token,
        ProcessElement $element,
        array $variables,
    ): ElementHandlerResult {
        $outgoingIds = $element->getOutgoingFlows();
        if (empty($outgoingIds)) {
            throw WorkflowException::noOutgoingFlow($element->element_id);
        }

        // اگر فقط یک خروجی دارد، همان را انتخاب کن
        if (count($outgoingIds) === 1) {
            return ElementHandlerResult::continueTo($outgoingIds);
        }

        $flows = $this->flowResolver->getFlows($instance->process_definition_id, $outgoingIds);

        // اولین flow غیر-default با condition=true
        $defaultFlow = null;
        foreach ($flows as $flow) {
            if ($flow['default']) {
                $defaultFlow = $flow;
                continue;
            }
            if (empty($flow['condition'])) continue;

            if ($this->evaluator->evaluateBoolean($flow['condition'], $variables)) {
                return ElementHandlerResult::continueTo([$flow['id']]);
            }
        }

        // اگر هیچ condition match نشد، default را برگردان
        if ($defaultFlow) {
            return ElementHandlerResult::continueTo([$defaultFlow['id']]);
        }

        throw WorkflowException::expressionEvaluationFailed(
            "ExclusiveGateway {$element->element_id}",
            'هیچ شرطی match نشد و default flow هم تعریف نشده.',
        );
    }
}
