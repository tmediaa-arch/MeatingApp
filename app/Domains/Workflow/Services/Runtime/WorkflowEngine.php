<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Services\Runtime;

use App\Domains\Workflow\Enums\ElementType;
use App\Domains\Workflow\Enums\ProcessInstanceStatus;
use App\Domains\Workflow\Enums\TokenStatus;
use App\Domains\Workflow\Exceptions\WorkflowException;
use App\Domains\Workflow\Models\ProcessElement;
use App\Domains\Workflow\Models\ProcessHistory;
use App\Domains\Workflow\Models\ProcessIncident;
use App\Domains\Workflow\Models\ProcessInstance;
use App\Domains\Workflow\Models\ProcessToken;
use App\Domains\Workflow\Services\Engine\FlowResolver;
use App\Domains\Workflow\Services\Engine\Handlers\ElementHandlerRegistry;
use App\Domains\Workflow\Services\Engine\Handlers\ElementHandlerResult;
use App\Domains\Workflow\Services\Engine\VariablesService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * موتور اجرای Workflow.
 *
 * این کلاس مسئول حرکت دادن tokenها در BPMN است.
 *
 * Lifecycle یک token در یک گام:
 *   1. token روی یک element فعال (status=active) است
 *   2. handler مربوطه را اجرا می‌کنیم
 *   3. بر اساس نتیجه:
 *      - continue: token را به element بعدی منتقل کن
 *      - wait: token را در waiting قرار بده
 *      - consumed: ممکن است join را trigger کند
 *      - split: چند token جدید بساز
 *      - complete: token کامل شد
 *
 * موتور تا زمانی ادامه می‌دهد که token در حالت active وجود داشته باشد.
 */
class WorkflowEngine
{
    /** حداکثر تعداد گام در یک اجرای پیوسته — جلوی infinite loop */
    private const MAX_STEPS_PER_RUN = 100;

    public function __construct(
        private readonly ElementHandlerRegistry $handlers,
        private readonly FlowResolver $flowResolver,
        private readonly VariablesService $variables,
    ) {
    }

    /**
     * گام‌برداری کامل یک instance — تمام tokenهای active را پیش می‌برد
     * تا همه به waiting یا پایان برسند.
     */
    public function runToCompletion(ProcessInstance $instance): void
    {
        if (!$instance->isActive()) {
            return;
        }

        $stepCount = 0;

        while ($stepCount < self::MAX_STEPS_PER_RUN) {
            $activeToken = $instance->tokens()
                ->where('status', TokenStatus::Active->value)
                ->first();

            if (!$activeToken) {
                // هیچ token فعالی نیست — یا waiting یا تمام
                $this->checkInstanceCompletion($instance);
                return;
            }

            $this->stepToken($activeToken);
            $stepCount++;
        }

        // به سقف رسیدیم
        Log::warning("WorkflowEngine: max steps reached for instance {$instance->instance_uuid}");
        $this->createIncident(
            $instance,
            null,
            'max_steps_exceeded',
            'تعداد گام‌های اجرا از سقف ' . self::MAX_STEPS_PER_RUN . ' عبور کرد.',
        );
    }

    /**
     * یک گام برای یک token.
     */
    public function stepToken(ProcessToken $token): void
    {
        $instance = $token->instance;
        $element = ProcessElement::where('process_definition_id', $instance->process_definition_id)
            ->where('element_id', $token->current_element_id)
            ->first();

        if (!$element) {
            throw WorkflowException::elementNotFound($token->current_element_id);
        }

        $variables = $this->variables->getAll($instance, $token);

        try {
            $handler = $this->handlers->get($element->element_type);

            ProcessHistory::log(
                instanceId: $instance->id,
                tokenId: $token->id,
                eventType: 'element_entered',
                elementId: $element->element_id,
                elementType: $element->element_type,
                elementName: $element->name,
            );

            $result = $handler->execute($instance, $token, $element, $variables);

            // ذخیره متغیرهای جدید
            if (!empty($result->variables)) {
                $this->variables->setMany($instance, $result->variables);
            }

            // اعمال نتیجه
            DB::transaction(function () use ($instance, $token, $element, $result) {
                $this->applyResult($instance, $token, $element, $result);
            });
        } catch (\Throwable $e) {
            Log::error("WorkflowEngine step failed", [
                'instance' => $instance->instance_uuid,
                'token' => $token->token_uuid,
                'element' => $element->element_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->createIncident(
                $instance,
                $token,
                'execution_error',
                $e->getMessage(),
                $element->element_id,
                $e->getTraceAsString(),
            );
        }
    }

    /**
     * انتقال token به element جدید (پس از sequence flow).
     */
    public function moveTokenAlongFlow(ProcessToken $token, string $flowId): void
    {
        $instance = $token->instance;
        $targetElementId = $this->flowResolver->getTargetElement(
            $instance->process_definition_id,
            $flowId,
        );

        if (!$targetElementId) {
            throw WorkflowException::elementNotFound("flow target: {$flowId}");
        }

        $targetElement = ProcessElement::where('process_definition_id', $instance->process_definition_id)
            ->where('element_id', $targetElementId)
            ->first();

        if (!$targetElement) {
            throw WorkflowException::elementNotFound($targetElementId);
        }

        $token->moveTo($targetElement->element_id, $targetElement->element_type);
        $token->activate(); // دوباره فعال شود برای گام بعد

        ProcessHistory::log(
            instanceId: $instance->id,
            tokenId: $token->id,
            eventType: 'flow_traversed',
            elementId: $flowId,
            payload: ['from' => $token->getOriginal('current_element_id'), 'to' => $targetElementId],
        );
    }

    /**
     * Wake up: یک token در waiting را activate کن (مثلاً پس از تکمیل UserTask).
     */
    public function wakeUpToken(ProcessToken $token): void
    {
        if ($token->status !== TokenStatus::Waiting) {
            return;
        }

        $token->activate();

        ProcessHistory::log(
            instanceId: $token->instance_id,
            tokenId: $token->id,
            eventType: 'token_resumed',
            elementId: $token->current_element_id,
        );

        // پس از wake up، element فعلی را به outgoing منتقل می‌کنیم
        $instance = $token->instance;
        $element = ProcessElement::where('process_definition_id', $instance->process_definition_id)
            ->where('element_id', $token->current_element_id)
            ->first();

        if ($element) {
            $outgoing = $element->getOutgoingFlows();
            if (count($outgoing) === 1) {
                $this->moveTokenAlongFlow($token, $outgoing[0]);
            }
            // برای multi-outgoing بعد از wake-up، token در همان element می‌ماند تا step دوباره اجرا شود
        }

        $this->runToCompletion($instance);
    }

    // ─────────── Private ───────────

    private function applyResult(
        ProcessInstance $instance,
        ProcessToken $token,
        ProcessElement $element,
        ElementHandlerResult $result,
    ): void {
        switch ($result->action) {
            case 'continue':
                $flowId = $result->nextFlowIds[0] ?? null;
                if (!$flowId) {
                    throw WorkflowException::noOutgoingFlow($element->element_id);
                }
                $this->moveTokenAlongFlow($token, $flowId);
                break;

            case 'wait':
                $token->status = TokenStatus::Waiting;
                $token->save();
                break;

            case 'consumed':
                $token->consume();
                $this->processJoinGateway($instance, $element, $token);
                break;

            case 'split':
                $this->splitToken($instance, $token, $element, $result->nextFlowIds);
                break;

            case 'complete':
                $token->complete();
                ProcessHistory::log(
                    instanceId: $instance->id,
                    tokenId: $token->id,
                    eventType: 'token_completed',
                    elementId: $element->element_id,
                    elementType: $element->element_type,
                );
                $this->checkInstanceCompletion($instance);
                break;
        }
    }

    /**
     * تقسیم یک token به چند token در split gateway.
     */
    private function splitToken(
        ProcessInstance $instance,
        ProcessToken $parentToken,
        ProcessElement $element,
        array $flowIds,
    ): void {
        $parentToken->consume();

        foreach ($flowIds as $flowId) {
            $targetElementId = $this->flowResolver->getTargetElement(
                $instance->process_definition_id,
                $flowId,
            );
            if (!$targetElementId) continue;

            $targetElement = ProcessElement::where('process_definition_id', $instance->process_definition_id)
                ->where('element_id', $targetElementId)
                ->first();
            if (!$targetElement) continue;

            ProcessToken::create([
                'instance_id' => $instance->id,
                'parent_token_id' => $parentToken->id,
                'current_element_id' => $targetElementId,
                'current_element_type' => $targetElement->element_type,
                'status' => TokenStatus::Active,
                'entered_current_element_at' => now(),
                'execution_path' => array_merge(
                    $parentToken->execution_path ?? [],
                    [['element_id' => $flowId, 'at' => now()->toIso8601String()]],
                ),
            ]);
        }

        ProcessHistory::log(
            instanceId: $instance->id,
            tokenId: $parentToken->id,
            eventType: 'token_split',
            elementId: $element->element_id,
            elementType: $element->element_type,
            payload: ['flows' => $flowIds],
        );
    }

    /**
     * بررسی join gateway: آیا همه siblings رسیده‌اند؟
     */
    private function processJoinGateway(
        ProcessInstance $instance,
        ProcessElement $element,
        ProcessToken $consumedToken,
    ): void {
        if ($element->element_type !== ElementType::ParallelGateway->value
            && $element->element_type !== ElementType::InclusiveGateway->value
        ) {
            return;
        }

        $incomingCount = count($element->getIncomingFlows());
        if ($incomingCount <= 1) return;

        // شمارش tokenهای consumed که از parent مشابه آمده‌اند و در همین element
        // برای parallel: همه incoming باید consume شده باشند
        $consumedHere = ProcessToken::where('instance_id', $instance->id)
            ->where('current_element_id', $element->element_id)
            ->where('status', TokenStatus::Consumed->value)
            ->count();

        if ($consumedHere >= $incomingCount) {
            // join تکمیل شد — یک token جدید برای outgoing بساز
            $outgoing = $element->getOutgoingFlows();
            foreach ($outgoing as $flowId) {
                $targetElementId = $this->flowResolver->getTargetElement(
                    $instance->process_definition_id,
                    $flowId,
                );
                if (!$targetElementId) continue;

                $target = ProcessElement::where('process_definition_id', $instance->process_definition_id)
                    ->where('element_id', $targetElementId)
                    ->first();
                if (!$target) continue;

                ProcessToken::create([
                    'instance_id' => $instance->id,
                    'parent_token_id' => $consumedToken->parent_token_id,
                    'current_element_id' => $targetElementId,
                    'current_element_type' => $target->element_type,
                    'status' => TokenStatus::Active,
                    'entered_current_element_at' => now(),
                ]);
            }

            ProcessHistory::log(
                instanceId: $instance->id,
                tokenId: null,
                eventType: 'token_joined',
                elementId: $element->element_id,
                elementType: $element->element_type,
                payload: ['merged_count' => $consumedHere],
            );
        }
    }

    /**
     * بررسی نهایی: اگر هیچ token alive نمانده، instance را Completed کن.
     */
    private function checkInstanceCompletion(ProcessInstance $instance): void
    {
        $aliveCount = $instance->tokens()
            ->whereIn('status', [TokenStatus::Active->value, TokenStatus::Waiting->value])
            ->count();

        if ($aliveCount === 0 && $instance->isActive()) {
            $instance->update([
                'status' => ProcessInstanceStatus::Completed,
                'completed_at' => now(),
            ]);

            ProcessHistory::log(
                instanceId: $instance->id,
                tokenId: null,
                eventType: 'instance_completed',
                payload: ['duration_seconds' => $instance->started_at?->diffInSeconds(now())],
            );
        }
    }

    private function createIncident(
        ProcessInstance $instance,
        ?ProcessToken $token,
        string $type,
        string $message,
        ?string $elementId = null,
        ?string $trace = null,
    ): void {
        ProcessIncident::create([
            'instance_id' => $instance->id,
            'token_id' => $token?->id,
            'incident_type' => $type,
            'element_id' => $elementId,
            'message' => $message,
            'stack_trace' => $trace,
            'status' => 'open',
        ]);

        // اگر token هست، آن را در waiting قرار بده تا تا حل incident، اجرا متوقف شود
        if ($token) {
            $token->update(['status' => TokenStatus::Waiting]);
        }

        ProcessHistory::log(
            instanceId: $instance->id,
            tokenId: $token?->id,
            eventType: 'incident_created',
            elementId: $elementId,
            payload: ['type' => $type, 'message' => $message],
        );
    }
}
