<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Services\Engine\Handlers;

use App\Domains\Workflow\Models\ProcessElement;
use App\Domains\Workflow\Models\ProcessInstance;
use App\Domains\Workflow\Models\ProcessToken;

/**
 * ReceiveTask: token تا دریافت یک message در waiting می‌ماند.
 */
class ReceiveTaskHandler implements ElementHandlerInterface
{
    public function execute(
        ProcessInstance $instance,
        ProcessToken $token,
        ProcessElement $element,
        array $variables,
    ): ElementHandlerResult {
        $messageName = $element->getMessageReference()
            ?? $element->properties['message_name']
            ?? $element->element_id;

        $token->setWaitingForMessage($messageName);
        return ElementHandlerResult::wait(waitFor: "message:{$messageName}");
    }
}
