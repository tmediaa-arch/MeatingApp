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

class ResumeInstanceAction
{
    public function __construct(
        private readonly WorkflowEngine $engine,
        private readonly AuditService $auditService,
    ) {
    }

    public function execute(ProcessInstance $instance, User $actor): ProcessInstance
    {
        if (!$instance->status->canTransitionTo(ProcessInstanceStatus::Running)) {
            throw WorkflowException::invalidTransition(
                $instance->status->value,
                ProcessInstanceStatus::Running->value,
            );
        }

        return DB::transaction(function () use ($instance, $actor) {
            $instance->update([
                'status' => ProcessInstanceStatus::Running,
                'suspended_at' => null,
            ]);

            ProcessHistory::log(
                instanceId: $instance->id,
                tokenId: null,
                eventType: 'instance_resumed',
                actorUserId: $actor->id,
            );

            $this->auditService->log(
                event: 'process_instance_resumed',
                auditable: $instance,
                description: "instance دوباره فعال شد",
                severity: 'notice',
            );

            // اجرای موتور برای ادامه
            $this->engine->runToCompletion($instance);

            return $instance->fresh();
        });
    }
}
