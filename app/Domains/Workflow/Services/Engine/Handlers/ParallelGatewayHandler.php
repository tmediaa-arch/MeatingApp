<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Services\Engine\Handlers;

use App\Domains\Workflow\Models\ProcessElement;
use App\Domains\Workflow\Models\ProcessInstance;
use App\Domains\Workflow\Models\ProcessToken;

/**
 * ParallelGateway (AND):
 *
 *  - SPLIT: یک incoming، چند outgoing → یک token به چند token تقسیم می‌شود
 *  - JOIN: چند incoming، یک outgoing → باید token از همه incoming ها برسد
 *
 * تشخیص split/join با شمارش incoming/outgoing:
 *   - 1 in, N out → split
 *   - N in, 1 out → join (token فعلی مصرف می‌شود؛ موتور Join را در سطح بالاتر بررسی می‌کند)
 *   - N in, M out → ترکیبی (split بعد از join)
 *
 * در حالت join، برخلاف exclusive، باید همه siblings در element برسند تا یک token جدید
 * تولید شود و forward بشود. این منطق در WorkflowEngine::processJoinGateway هندل می‌شود.
 */
class ParallelGatewayHandler implements ElementHandlerInterface
{
    public function execute(
        ProcessInstance $instance,
        ProcessToken $token,
        ProcessElement $element,
        array $variables,
    ): ElementHandlerResult {
        $incoming = $element->getIncomingFlows();
        $outgoing = $element->getOutgoingFlows();

        // join: token را consume می‌کنیم؛ Engine باید چک کند آیا همه رسیدند
        if (count($incoming) > 1 && count($outgoing) === 1) {
            return ElementHandlerResult::consumed();
        }

        // split: همه outgoing flows را به‌عنوان token جدید تولید می‌کنیم
        if (count($incoming) <= 1 && count($outgoing) > 1) {
            return ElementHandlerResult::split($outgoing);
        }

        // pass-through (یک in یک out)
        if (count($outgoing) === 1) {
            return ElementHandlerResult::continueTo($outgoing);
        }

        // N×M (در عمل نادر)
        return ElementHandlerResult::consumed();
    }
}
