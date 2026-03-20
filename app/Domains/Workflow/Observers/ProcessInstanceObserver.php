<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Observers;

use App\Domains\Workflow\Enums\ProcessInstanceStatus;
use App\Domains\Workflow\Exceptions\WorkflowException;
use App\Domains\Workflow\Models\ProcessInstance;

class ProcessInstanceObserver
{
    public function updating(ProcessInstance $instance): void
    {
        if (!$instance->isDirty('status')) return;

        $old = $instance->getOriginal('status');
        $new = $instance->status;

        $oldEnum = $old instanceof ProcessInstanceStatus
            ? $old
            : ProcessInstanceStatus::tryFrom((string) $old);
        $newEnum = $new instanceof ProcessInstanceStatus
            ? $new
            : ProcessInstanceStatus::tryFrom((string) $new);

        if ($oldEnum && $newEnum && !$oldEnum->canTransitionTo($newEnum)) {
            throw WorkflowException::invalidTransition($oldEnum->value, $newEnum->value);
        }
    }
}
