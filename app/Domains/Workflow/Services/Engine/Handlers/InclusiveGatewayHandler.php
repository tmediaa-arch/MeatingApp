<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Services\Engine\Handlers;

use App\Domains\Workflow\Models\ProcessElement;
use App\Domains\Workflow\Models\ProcessInstance;
use App\Domains\Workflow\Models\ProcessToken;
use App\Domains\Workflow\Services\Engine\ExpressionEvaluator;
use App\Domains\Workflow\Services\Engine\FlowResolver;

/**
 * InclusiveGateway (OR):
 *  - تمام flows با condition=true انتخاب می‌شوند
 *  - اگر هیچ‌کدام نشدند، default
 */
class InclusiveGatewayHandler implements ElementHandlerInterface
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
        $flows = $this->flowResolver->getFlows($instance->process_definition_id, $outgoingIds);

        $matched = [];
        $defaultFlow = null;

        foreach ($flows as $flow) {
            if ($flow['default']) {
                $defaultFlow = $flow;
                continue;
            }
            if (empty($flow['condition'])) {
                $matched[] = $flow['id'];
                continue;
            }
            if ($this->evaluator->evaluateBoolean($flow['condition'], $variables)) {
                $matched[] = $flow['id'];
            }
        }

        if (empty($matched) && $defaultFlow) {
            $matched[] = $defaultFlow['id'];
        }

        if (count($matched) === 1) {
            return ElementHandlerResult::continueTo($matched);
        }
        if (count($matched) > 1) {
            return ElementHandlerResult::split($matched);
        }

        return ElementHandlerResult::consumed();
    }
}
