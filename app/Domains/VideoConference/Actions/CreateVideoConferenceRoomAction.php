<?php

declare(strict_types=1);

namespace App\Domains\VideoConference\Actions;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\VideoConference\DTOs\CreateRoomRequest;
use App\Domains\VideoConference\Enums\VideoConferenceRoomStatus;
use App\Domains\VideoConference\Exceptions\VideoConferenceException;
use App\Domains\VideoConference\Models\VideoConferenceProvider;
use App\Domains\VideoConference\Models\VideoConferenceRoom;
use App\Domains\VideoConference\Services\VideoConferenceProviderManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ایجاد یک اتاق ویدئوکنفرانس برای یک جلسه.
 *
 * مراحل:
 *  1. انتخاب provider (یا پیش‌فرض organization)
 *  2. بررسی محدودیت همزمانی
 *  3. فراخوانی adapter::createRoom
 *  4. ذخیره در DB
 *  5. ثبت در audit
 */
class CreateVideoConferenceRoomAction
{
    public function __construct(
        private readonly VideoConferenceProviderManager $providerManager,
        private readonly AuditService $auditService,
    ) {
    }

    /**
     * @param array $data
     *   - meeting_id?     (اختیاری)
     *   - provider_id?    (اگر null، پیش‌فرض organization استفاده می‌شود)
     *   - subject         (اجباری)
     *   - max_participants?
     *   - require_password? (default false)
     *   - waiting_room_enabled? (default false)
     *   - recording_enabled?    (default false)
     *   - scheduled_start_at?
     *   - scheduled_end_at?
     *   - organization_id (اجباری اگر provider_id نباشد)
     */
    public function execute(array $data): VideoConferenceRoom
    {
        $meeting = isset($data['meeting_id']) ? Meeting::find($data['meeting_id']) : null;

        // اگر جلسه قبلاً اتاق دارد، اجازه نده
        if ($meeting && $meeting->videoConferenceRoom()->exists()) {
            throw VideoConferenceException::meetingAlreadyHasRoom();
        }

        // resolve provider
        $provider = isset($data['provider_id'])
            ? VideoConferenceProvider::findOrFail($data['provider_id'])
            : $this->providerManager->findDefaultForOrganization(
                $data['organization_id'] ?? $meeting?->organization_id,
            );

        if (!$provider->isUsable()) {
            throw VideoConferenceException::providerUnhealthy($provider->name);
        }

        if ($provider->hasReachedConcurrentLimit()) {
            throw VideoConferenceException::maxConcurrentReached(
                $provider->name,
                $provider->max_concurrent_meetings,
            );
        }

        return DB::transaction(function () use ($data, $provider, $meeting) {
            $internalId = (string) Str::uuid();

            // فراخوانی adapter
            $adapter = $this->providerManager->resolve($provider);
            $request = new CreateRoomRequest(
                subject: $data['subject'],
                internalRoomId: $internalId,
                scheduledStartAt: $data['scheduled_start_at'] ?? null,
                scheduledEndAt: $data['scheduled_end_at'] ?? null,
                maxParticipants: $data['max_participants']
                    ?? $provider->max_participants_per_meeting,
                requirePassword: $data['require_password'] ?? false,
                waitingRoomEnabled: $data['waiting_room_enabled'] ?? false,
                recordingEnabled: ($data['recording_enabled'] ?? false) && $adapter->supportsRecording(),
                welcomeMessage: $data['welcome_message'] ?? null,
            );

            $response = $adapter->createRoom($request);

            // ذخیره در DB
            $room = VideoConferenceRoom::create([
                'room_uuid' => $internalId,
                'meeting_id' => $meeting?->id,
                'provider_id' => $provider->id,
                'driver' => $provider->driver,
                'external_room_id' => $response->externalRoomId,
                'host_url' => $response->hostUrl,
                'attendee_url' => $response->attendeeUrl,
                'moderator_password' => $response->moderatorPassword,
                'attendee_password' => $response->attendeePassword,
                'subject' => $data['subject'],
                'max_participants' => $request->maxParticipants,
                'require_password' => $request->requirePassword,
                'waiting_room_enabled' => $request->waitingRoomEnabled,
                'recording_enabled' => $request->recordingEnabled,
                'scheduled_start_at' => $request->scheduledStartAt,
                'scheduled_end_at' => $request->scheduledEndAt,
                'status' => VideoConferenceRoomStatus::Scheduled,
                'provider_metadata' => $response->providerMetadata,
                'created_by_user_id' => $data['created_by_user_id'] ?? auth()->id(),
            ]);

            $this->auditService->log(
                event: 'video_conference_room_created',
                auditable: $room,
                description: sprintf(
                    'اتاق ویدئوکنفرانس برای موضوع "%s" در driver %s ایجاد شد',
                    $data['subject'],
                    $provider->driver->value,
                ),
                context: [
                    'provider_id' => $provider->id,
                    'meeting_id' => $meeting?->id,
                ],
                severity: 'notice',
            );

            return $room;
        });
    }
}
