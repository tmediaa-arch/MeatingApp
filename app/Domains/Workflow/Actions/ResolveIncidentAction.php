<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Actions;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Identity\Models\User;
use App\Domains\Workflow\Models\ProcessHistory;
use App\Domains\Workflow\Models\ProcessIncident;
use App\Domains\Workflow\Services\Runtime\WorkflowEngine;
use Illuminate\Support\Facades\DB;

class ResolveIncidentAction
{
    public function __construct(
        private readonly WorkflowEngine $engine,
        private readonly AuditService $auditService,
    ) {
    }

    /**
     * حل یک incident.
     *
     * اگر $retry=true باشد، token مرتبط دوباره activate می‌شود.
     */
    public function execute(
        ProcessIncident $incident,
        User $resolver,
        bool $retry = false,
        ?string $note = null,
    ): ProcessIncident {
        if (!$incident->isOpen()) {
            return $incident;
        }

        return DB::transaction(function () use ($incident, $resolver, $retry, $note) {
            $incident->update([
                'status' => 'resolved',
                'resolved_by_user_id' => $resolver->id,
                'resolved_at' => now(),
                'resolution_note' => $note,
            ]);

            ProcessHistory::log(
                instanceId: $incident->instance_id,
                tokenId: $incident->token_id,
                eventType: 'incident_resolved',
                elementId: $incident->element_id,
                payload: ['retry' => $retry, 'note' => $note],
                actorUserId: $resolver->id,
            );

            $this->auditService->log(
                event: 'workflow_incident_resolved',
                auditable: $incident,
                description: "Incident '{$incident->incident_type}' حل شد",
                context: ['retry' => $retry],
                severity: 'notice',
            );

            // retry: token را activate کن و engine را اجرا کن
            if ($retry && $incident->token) {
                $incident->token->activate();
                $this->engine->runToCompletion($incident->instance);
            }

            return $incident->fresh();
        });
    }
}
