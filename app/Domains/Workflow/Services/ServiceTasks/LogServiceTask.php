<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Services\ServiceTasks;

use App\Domains\Workflow\Models\ProcessHistory;
use App\Domains\Workflow\Models\ProcessInstance;
use App\Domains\Workflow\Models\ProcessToken;
use Illuminate\Support\Facades\Log;

/**
 * Service Task: لاگ یک پیام در history و log فایل.
 *
 * Config:
 *   message:  پیام (می‌تواند expression باشد)
 *   level:    debug / info / warning / error (پیش‌فرض info)
 */
class LogServiceTask implements ServiceTaskInterface
{
    public static function key(): string
    {
        return 'log';
    }

    public static function description(): string
    {
        return 'لاگ یک پیام در history و log فایل';
    }

    public static function configSchema(): array
    {
        return [
            'message' => ['type' => 'string', 'required' => true, 'label' => 'پیام'],
            'level' => [
                'type' => 'string',
                'required' => false,
                'label' => 'سطح',
                'options' => ['debug', 'info', 'warning', 'error'],
            ],
        ];
    }

    public function execute(
        ProcessInstance $instance,
        ProcessToken $token,
        array $config,
        array $variables,
    ): array {
        $message = $config['message'] ?? '(بدون پیام)';
        $level = $config['level'] ?? 'info';

        Log::log($level, "[workflow:{$instance->instance_uuid}] {$message}", [
            'token' => $token->token_uuid,
            'element' => $token->current_element_id,
        ]);

        ProcessHistory::log(
            instanceId: $instance->id,
            tokenId: $token->id,
            eventType: 'log',
            elementId: $token->current_element_id,
            elementType: $token->current_element_type,
            payload: ['message' => $message, 'level' => $level],
        );

        return [];
    }
}
