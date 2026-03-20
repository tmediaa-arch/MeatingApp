<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Observers;

use App\Domains\Workflow\Enums\ProcessDefinitionStatus;
use App\Domains\Workflow\Exceptions\WorkflowException;
use App\Domains\Workflow\Models\ProcessDefinition;

class ProcessDefinitionObserver
{
    /**
     * Defence-in-depth برای transitions ProcessDefinitionStatus.
     */
    public function updating(ProcessDefinition $definition): void
    {
        if (!$definition->isDirty('status')) return;

        $old = $definition->getOriginal('status');
        $new = $definition->status;

        $oldEnum = $old instanceof ProcessDefinitionStatus
            ? $old
            : ProcessDefinitionStatus::tryFrom((string) $old);
        $newEnum = $new instanceof ProcessDefinitionStatus
            ? $new
            : ProcessDefinitionStatus::tryFrom((string) $new);

        if ($oldEnum && $newEnum && !$oldEnum->canTransitionTo($newEnum)) {
            throw WorkflowException::invalidTransition($oldEnum->value, $newEnum->value);
        }
    }
}
