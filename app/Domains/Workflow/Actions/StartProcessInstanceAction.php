<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Actions;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Identity\Models\User;
use App\Domains\Workflow\Enums\ProcessInstanceStatus;
use App\Domains\Workflow\Enums\TokenStatus;
use App\Domains\Workflow\Exceptions\WorkflowException;
use App\Domains\Workflow\Models\ProcessDefinition;
use App\Domains\Workflow\Models\ProcessHistory;
use App\Domains\Workflow\Models\ProcessInstance;
use App\Domains\Workflow\Models\ProcessToken;
use App\Domains\Workflow\Services\Engine\VariablesService;
use App\Domains\Workflow\Services\Runtime\WorkflowEngine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * شروع یک instance جدید از یک Published ProcessDefinition.
 *
 * مراحل:
 *  1. اعتبارسنجی که definition published است
 *  2. ایجاد instance با business_key و subject
 *  3. ایجاد متغیرهای اولیه
 *  4. ایجاد token اول روی startEvent
 *  5. فراخوانی WorkflowEngine::runToCompletion
 */
class StartProcessInstanceAction
{
    public function __construct(
        private readonly WorkflowEngine $engine,
        private readonly VariablesService $variables,
        private readonly AuditService $auditService,
    ) {
    }

    /**
     * @param array $data
     *   - process_definition_id
     *   - business_key?
     *   - subject?           (Eloquent model)
     *   - starter?           (User)
     *   - variables?         (array)
     *   - priority?          (low/normal/high/critical)
     *   - sla_due_at?        (DateTime)
     *   - context?           (array)
     */
    public function execute(array $data): ProcessInstance
    {
        /** @var ProcessDefinition $definition */
        $definition = ProcessDefinition::findOrFail($data['process_definition_id']);

        if (!$definition->canStartInstance()) {
            throw WorkflowException::processNotPublished($definition->process_key);
        }

        $startElement = $definition->getStartElement();
        if (!$startElement) {
            throw WorkflowException::noStartEvent();
        }

        return DB::transaction(function () use ($data, $definition, $startElement) {
            /** @var User|null $starter */
            $starter = $data['starter'] ?? auth()->user();

            /** @var Model|null $subject */
            $subject = $data['subject'] ?? null;

            $instance = ProcessInstance::create([
                'process_definition_id' => $definition->id,
                'process_key' => $definition->process_key,
                'process_version' => $definition->version,
                'organization_id' => $definition->organization_id,
                'business_key' => $data['business_key'] ?? null,
                'subject_type' => $subject ? get_class($subject) : null,
                'subject_id' => $subject?->getKey(),
                'status' => ProcessInstanceStatus::Running,
                'started_at' => now(),
                'priority' => $data['priority'] ?? 'normal',
                'sla_due_at' => $data['sla_due_at'] ?? null,
                'starter_user_id' => $starter?->id,
                'start_variables' => $data['variables'] ?? [],
                'context' => $data['context'] ?? null,
            ]);

            // ذخیره متغیرهای اولیه
            if (!empty($data['variables'])) {
                $this->variables->setMany($instance, $data['variables']);
            }

            // ایجاد token اول روی startEvent
            $token = ProcessToken::create([
                'instance_id' => $instance->id,
                'current_element_id' => $startElement->element_id,
                'current_element_type' => $startElement->element_type,
                'status' => TokenStatus::Active,
                'entered_current_element_at' => now(),
                'execution_path' => [],
            ]);

            ProcessHistory::log(
                instanceId: $instance->id,
                tokenId: $token->id,
                eventType: 'instance_started',
                elementId: $startElement->element_id,
                elementType: $startElement->element_type,
                payload: [
                    'process_key' => $definition->process_key,
                    'process_version' => $definition->version,
                    'starter_user_id' => $starter?->id,
                    'variables' => array_keys($data['variables'] ?? []),
                ],
                actorUserId: $starter?->id,
            );

            $this->auditService->log(
                event: 'process_instance_started',
                auditable: $instance,
                description: sprintf(
                    "instance جدید از فرایند '%s' v%d شروع شد",
                    $definition->process_key,
                    $definition->version,
                ),
                context: [
                    'instance_uuid' => $instance->instance_uuid,
                    'business_key' => $instance->business_key,
                ],
                severity: 'notice',
            );

            // اجرای موتور تا اولین wait/end
            $this->engine->runToCompletion($instance);

            return $instance->fresh();
        });
    }
}
