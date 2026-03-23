<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Services\ServiceTasks;

use App\Domains\VideoConference\Actions\CreateVideoConferenceRoomAction;
use App\Domains\Workflow\Models\ProcessInstance;
use App\Domains\Workflow\Models\ProcessToken;

/**
 * Service Task: ایجاد اتاق ویدئوکنفرانس از فرایند.
 *
 * این یک پل بین Phase 4 (BPMN) و Phase 5 (VideoConference) است —
 * فرایندها می‌توانند به‌صورت خودکار اتاق ایجاد کنند.
 *
 * Config:
 *   subject:                 موضوع جلسه (می‌تواند expression باشد)
 *   provider_id?:            id provider (اختیاری — اگر نباشد، پیش‌فرض org)
 *   meeting_id?:             id جلسه (می‌تواند expression باشد)
 *   max_participants?:       int
 *   recording_enabled?:      bool
 *   waiting_room_enabled?:   bool
 */
class CreateVideoConferenceServiceTask implements ServiceTaskInterface
{
    public function __construct(private readonly CreateVideoConferenceRoomAction $createAction)
    {
    }

    public static function key(): string
    {
        return 'create_video_conference';
    }

    public static function description(): string
    {
        return 'ایجاد اتاق ویدئوکنفرانس برای جلسه';
    }

    public static function configSchema(): array
    {
        return [
            'subject' => ['type' => 'string', 'required' => true, 'label' => 'موضوع'],
            'provider_id' => ['type' => 'expression', 'required' => false, 'label' => 'provider_id'],
            'meeting_id' => ['type' => 'expression', 'required' => false, 'label' => 'meeting_id'],
            'max_participants' => ['type' => 'integer', 'required' => false],
            'recording_enabled' => ['type' => 'boolean', 'required' => false],
            'waiting_room_enabled' => ['type' => 'boolean', 'required' => false],
        ];
    }

    public function execute(
        ProcessInstance $instance,
        ProcessToken $token,
        array $config,
        array $variables,
    ): array {
        $subject = $config['subject'] ?? "جلسه — فرایند {$instance->process_key}";

        $room = $this->createAction->execute([
            'organization_id' => $instance->organization_id,
            'subject' => $subject,
            'provider_id' => isset($config['provider_id']) && is_numeric($config['provider_id'])
                ? (int) $config['provider_id'] : null,
            'meeting_id' => isset($config['meeting_id']) && is_numeric($config['meeting_id'])
                ? (int) $config['meeting_id'] : null,
            'max_participants' => isset($config['max_participants'])
                ? (int) $config['max_participants'] : null,
            'recording_enabled' => (bool) ($config['recording_enabled'] ?? false),
            'waiting_room_enabled' => (bool) ($config['waiting_room_enabled'] ?? false),
            'created_by_user_id' => $instance->starter_user_id,
        ]);

        return [
            '_last_vc_room_id' => $room->id,
            '_last_vc_room_uuid' => $room->room_uuid,
            '_last_vc_host_url' => $room->host_url,
            '_last_vc_attendee_url' => $room->attendee_url,
        ];
    }
}
