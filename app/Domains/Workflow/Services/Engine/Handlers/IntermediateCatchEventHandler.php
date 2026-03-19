<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Services\Engine\Handlers;

use App\Domains\Workflow\Models\ProcessElement;
use App\Domains\Workflow\Models\ProcessInstance;
use App\Domains\Workflow\Models\ProcessToken;

/**
 * IntermediateCatchEvent:
 *  - Timer event: token در waiting قرار می‌گیرد تا زمان مشخص
 *  - Message event: token منتظر دریافت یک message
 */
class IntermediateCatchEventHandler implements ElementHandlerInterface
{
    public function execute(
        ProcessInstance $instance,
        ProcessToken $token,
        ProcessElement $element,
        array $variables,
    ): ElementHandlerResult {
        // Timer event
        if ($duration = $element->getTimerDuration()) {
            $until = $this->parseDuration($duration);
            $token->setWaitingForTimer($until);
            return ElementHandlerResult::wait(waitFor: 'timer', until: $until);
        }

        // Message event
        if ($messageName = $element->getMessageReference()) {
            $token->setWaitingForMessage($messageName);
            return ElementHandlerResult::wait(waitFor: "message:{$messageName}");
        }

        // default: continue (event بدون شرط — احتمالاً خطای BPMN)
        return ElementHandlerResult::continueTo($element->getOutgoingFlows());
    }

    /**
     * پارس ISO 8601 duration (PT1H, P1D, PT30M, ...).
     */
    private function parseDuration(string $duration): \DateTimeImmutable
    {
        try {
            $interval = new \DateInterval($duration);
            return (new \DateTimeImmutable())->add($interval);
        } catch (\Throwable) {
            // fallback: تلاش برای پارس ساده
            if (preg_match('/^(\d+)([smhd])$/i', $duration, $m)) {
                $n = (int) $m[1];
                $unit = strtolower($m[2]);
                $seconds = match ($unit) {
                    's' => $n,
                    'm' => $n * 60,
                    'h' => $n * 3600,
                    'd' => $n * 86400,
                    default => $n,
                };
                return (new \DateTimeImmutable())->modify("+{$seconds} seconds");
            }
            // last resort
            return (new \DateTimeImmutable())->modify('+1 hour');
        }
    }
}
