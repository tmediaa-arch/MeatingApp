<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Services\Engine\Handlers;

use App\Domains\Workflow\Models\ProcessElement;
use App\Domains\Workflow\Models\ProcessInstance;
use App\Domains\Workflow\Models\ProcessToken;

/**
 * هر نوع element در BPMN باید یک Handler داشته باشد که می‌داند
 * چطور آن element را اجرا کند.
 *
 * متد execute() می‌تواند یکی از این‌ها را برگرداند:
 *  - 'continue': token را به element بعدی منتقل کن
 *  - 'wait': token در حالت waiting قرار گیرد
 *  - 'consumed': token مصرف شد (مثلاً در join gateway)
 *  - 'split': چند token جدید تولید شد (parallel split)
 *  - 'complete': این مسیر به پایان رسید
 */
interface ElementHandlerInterface
{
    /**
     * اجرای element روی یک token.
     *
     * @return ElementHandlerResult
     */
    public function execute(
        ProcessInstance $instance,
        ProcessToken $token,
        ProcessElement $element,
        array $variables,
    ): ElementHandlerResult;
}
