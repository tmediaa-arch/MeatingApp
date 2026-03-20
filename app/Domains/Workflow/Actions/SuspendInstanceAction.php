<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Actions;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Identity\Models\User;
use App\Domains\Workflow\Enums\ProcessInstanceStatus;
use App\Domains\Workflow\Exceptions\WorkflowException;
use App\Domains\Workflow\Models\ProcessHistory;
use App\Domains\Workflow\Models\ProcessInstance;
use App\Domains\Workflow\Services\Runtime\WorkflowEngine;
use Illuminate\Support\Facades\DB;

class SuspendInstanceAction
{
    public function __construct(private readonly AuditService $auditService)
    {
    }

    public function execute(ProcessInstance $instance, User $actor, ?string $reason = null): ProcessInstance
    {
        if (!$instance->status->canTransitionTo(ProcessInstanceStatus::Suspended)) {
            throw WorkflowException::invalidTransition(
                $instance->status->value,
                ProcessInstanceStatus::Suspended->value,
            );
        }

        return DB::transaction(function () use ($instance, $actor, $reason) {
            $instance->update([
                'status' => ProcessInstanceStatus::Suspended,
                'suspended_at' => now(),
            ]);

            ProcessHistory::log(
                instanceId: $instance->id,
                tokenId: null,
                eventType: 'instance_suspended',
                payload: ['reason' => $reason],
                actorUserId: $actor->id,
            );

            $this->auditService->log(
                event: 'process_instance_suspended',
                auditable: $instance,
                description: "instance متوقف شد",
                context: ['reason' => $reason],
                severity: 'notice',
            );

            return $instance->fresh();
        });
    }
}
