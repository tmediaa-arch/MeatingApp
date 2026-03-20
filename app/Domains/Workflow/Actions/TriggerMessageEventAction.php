<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Actions;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Workflow\Enums\TokenStatus;
use App\Domains\Workflow\Models\ProcessHistory;
use App\Domains\Workflow\Models\ProcessToken;
use App\Domains\Workflow\Services\Engine\VariablesService;
use App\Domains\Workflow\Services\Runtime\WorkflowEngine;
use Illuminate\Support\Facades\DB;

/**
 * Trigger یک Message event: tokenهایی که منتظر این message هستند را wake کن.
 */
class TriggerMessageEventAction
{
    public function __construct(
        private readonly WorkflowEngine $engine,
        private readonly VariablesService $variables,
        private readonly AuditService $auditService,
    ) {
    }

    /**
     * @param string $messageName نام message (مثلاً 'approval_received')
     * @param array $payload متغیرهایی که با message ارسال می‌شوند
     * @param int|null $instanceId اختیاری — برای محدود کردن به یک instance خاص
     * @return int تعداد tokenهایی که trigger شدند
     */
    public function execute(string $messageName, array $payload = [], ?int $instanceId = null): int
    {
        $query = ProcessToken::query()
            ->where('status', TokenStatus::Waiting->value)
            ->where('wait_for_message', $messageName);

        if ($instanceId) {
            $query->where('instance_id', $instanceId);
        }

        $tokens = $query->with('instance')->get();

        if ($tokens->isEmpty()) {
            return 0;
        }

        DB::transaction(function () use ($tokens, $messageName, $payload) {
            foreach ($tokens as $token) {
                if (!empty($payload)) {
                    $this->variables->setMany($token->instance, $payload);
                }

                ProcessHistory::log(
                    instanceId: $token->instance_id,
                    tokenId: $token->id,
                    eventType: 'message_received',
                    elementId: $token->current_element_id,
                    payload: ['message_name' => $messageName, 'payload_keys' => array_keys($payload)],
                );

                $this->engine->wakeUpToken($token);
            }
        });

        return $tokens->count();
    }
}
