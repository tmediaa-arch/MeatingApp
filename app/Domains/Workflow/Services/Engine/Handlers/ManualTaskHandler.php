<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Services\Engine\Handlers;

use App\Domains\Workflow\Models\ProcessElement;
use App\Domains\Workflow\Models\ProcessInstance;
use App\Domains\Workflow\Models\ProcessToken;

/**
 * ManualTask: مشابه UserTask ولی بدون فرم — صرفاً انتظار اقدام دستی.
 */
class ManualTaskHandler implements ElementHandlerInterface
{
    public function execute(
        ProcessInstance $instance,
        ProcessToken $token,
        ProcessElement $element,
        array $variables,
    ): ElementHandlerResult {
        return ElementHandlerResult::wait();
    }
}
