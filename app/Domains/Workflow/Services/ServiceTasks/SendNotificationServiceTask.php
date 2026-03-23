<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Services\ServiceTasks;

use App\Domains\Notifications\Services\NotificationDispatcher;
use App\Domains\Workflow\Models\ProcessInstance;
use App\Domains\Workflow\Models\ProcessToken;

/**
 * Service Task: ارسال notification با template key.
 *
 * Config:
 *   template_key:   کلید قالب (مثلاً 'meeting.invitation')
 *   recipient:      user id یا کلیدی از متغیرها
 *   variables:      key-value از متغیرها برای رندر قالب
 */
class SendNotificationServiceTask implements ServiceTaskInterface
{
    public function __construct(private readonly NotificationDispatcher $dispatcher)
    {
    }

    public static function key(): string
    {
        return 'send_notification';
    }

    public static function description(): string
    {
        return 'ارسال اعلان با استفاده از قالب';
    }

    public static function configSchema(): array
    {
        return [
            'template_key' => [
                'type' => 'string',
                'required' => true,
                'label' => 'کلید قالب',
            ],
            'recipient' => [
                'type' => 'expression',
                'required' => true,
                'label' => 'گیرنده (user_id یا expression)',
            ],
            'variables' => [
                'type' => 'json',
                'required' => false,
                'label' => 'متغیرهای قالب',
            ],
        ];
    }

    public function execute(
        ProcessInstance $instance,
        ProcessToken $token,
        array $config,
        array $variables,
    ): array {
        $templateKey = $config['template_key'] ?? null;
        $recipient = $config['recipient'] ?? null;

        if (!$templateKey || !$recipient) {
            throw new \DomainException('template_key و recipient الزامی هستند.');
        }

        $templateVars = $config['variables'] ?? [];

        // تشخیص user_id
        $userId = is_numeric($recipient) ? (int) $recipient : null;
        if (!$userId) {
            // ممکن است key یک متغیر باشد
            $resolved = $variables[$recipient] ?? null;
            $userId = is_numeric($resolved) ? (int) $resolved : null;
        }

        if (!$userId) {
            throw new \DomainException("نمی‌توان recipient '{$recipient}' را به user_id تبدیل کرد.");
        }

        $outboxIds = $this->dispatcher->send(
            templateKey: $templateKey,
            recipient: $userId,
            variables: $templateVars,
            notifiable: $instance,
        );

        return [
            '_last_notification_ids' => $outboxIds,
        ];
    }
}
