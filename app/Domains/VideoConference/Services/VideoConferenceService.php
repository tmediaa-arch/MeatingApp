<?php

declare(strict_types=1);

namespace App\Domains\VideoConference\Services;

use App\Domains\VideoConference\DTOs\JoinUrlRequest;
use App\Domains\VideoConference\Enums\AttendanceRole;
use App\Domains\VideoConference\Enums\VideoConferenceRoomStatus;
use App\Domains\VideoConference\Exceptions\VideoConferenceException;
use App\Domains\VideoConference\Models\VideoConferenceAttendance;
use App\Domains\VideoConference\Models\VideoConferenceRoom;
use Illuminate\Support\Facades\DB;

/**
 * یک facade مرکزی برای کار با اتاق‌های ویدئوکنفرانس.
 *
 * این service واسطه بین domain logic و adapter ها است.
 */
class VideoConferenceService
{
    public function __construct(
        private readonly VideoConferenceProviderManager $providerManager,
    ) {
    }

    /**
     * تولید لینک ورود برای یک کاربر مشخص.
     */
    public function generateJoinUrl(
        VideoConferenceRoom $room,
        string $displayName,
        AttendanceRole $role,
        ?string $email = null,
        ?int $userId = null,
    ): string {
        $adapter = $this->providerManager->resolve($room->provider);

        return $adapter->generateJoinUrl(new JoinUrlRequest(
            externalRoomId: $room->external_room_id,
            displayName: $displayName,
            role: $role,
            email: $email,
            userId: $userId,
            metadata: [
                'password' => $role === AttendanceRole::Host || $role === AttendanceRole::Moderator
                    ? $room->moderator_password
                    : $room->attendee_password,
            ],
        ));
    }

    /**
     * شروع اتاق — معمولاً وقتی میزبان وارد می‌شود.
     */
    public function markStarted(VideoConferenceRoom $room): VideoConferenceRoom
    {
        if (!$room->status->canTransitionTo(VideoConferenceRoomStatus::InProgress)) {
            throw VideoConferenceException::invalidTransition(
                $room->status->value,
                VideoConferenceRoomStatus::InProgress->value,
            );
        }

        $room->update([
            'status' => VideoConferenceRoomStatus::InProgress,
            'actual_start_at' => now(),
        ]);
        return $room->fresh();
    }

    /**
     * پایان اتاق + sync با provider.
     */
    public function endRoom(VideoConferenceRoom $room): VideoConferenceRoom
    {
        if ($room->status->isTerminal()) {
            return $room;
        }

        try {
            $adapter = $this->providerManager->resolve($room->provider);
            $adapter->endRoom($room->external_room_id);
        } catch (\Throwable $e) {
            // log اما continue — می‌خواهیم در DB ببنیدیمش حتی اگر provider خطا داد
            \Log::warning("Failed to end room {$room->room_uuid} in provider", ['error' => $e->getMessage()]);
        }

        $room->update([
            'status' => VideoConferenceRoomStatus::Ended,
            'actual_end_at' => now(),
        ]);

        return $room->fresh();
    }

    /**
     * Sync وضعیت اتاق با provider — برای health monitor.
     */
    public function syncStatus(VideoConferenceRoom $room): VideoConferenceRoom
    {
        try {
            $adapter = $this->providerManager->resolve($room->provider);
            $status = $adapter->getRoomStatus($room->external_room_id);

            $update = ['provider_metadata' => $status->metadata];

            if ($status->isActive && $room->status === VideoConferenceRoomStatus::Scheduled) {
                $update['status'] = VideoConferenceRoomStatus::InProgress;
                $update['actual_start_at'] = $status->startedAt ?? now();
            } elseif (!$status->isActive && $room->status === VideoConferenceRoomStatus::InProgress) {
                $update['status'] = VideoConferenceRoomStatus::Ended;
                $update['actual_end_at'] = now();
            }

            $room->update($update);
        } catch (\Throwable $e) {
            \Log::error("Failed to sync room {$room->room_uuid}", ['error' => $e->getMessage()]);
        }

        return $room->fresh();
    }

    /**
     * ثبت یک رویداد attendance (join یا leave).
     */
    public function recordAttendance(
        VideoConferenceRoom $room,
        string $displayName,
        AttendanceRole $role,
        string $eventType,
        ?int $userId = null,
        ?int $employeeId = null,
        ?string $email = null,
        ?string $clientIp = null,
        array $metadata = [],
    ): VideoConferenceAttendance {
        return VideoConferenceAttendance::create([
            'room_id' => $room->id,
            'meeting_id' => $room->meeting_id,
            'user_id' => $userId,
            'employee_id' => $employeeId,
            'display_name' => $displayName,
            'email' => $email,
            'role' => $role,
            'event_type' => $eventType,
            'occurred_at' => now(),
            'client_ip' => $clientIp,
            'metadata' => $metadata,
        ]);
    }

    /**
     * شروع ضبط.
     */
    public function startRecording(VideoConferenceRoom $room): void
    {
        $adapter = $this->providerManager->resolve($room->provider);
        $adapter->startRecording($room->external_room_id);

        $room->update(['recording_status' => 'recording']);
    }

    /**
     * توقف ضبط.
     */
    public function stopRecording(VideoConferenceRoom $room): void
    {
        $adapter = $this->providerManager->resolve($room->provider);
        $adapter->stopRecording($room->external_room_id);

        $room->update(['recording_status' => 'processing']);
    }

    /**
     * دریافت اطلاعات ضبط نهایی از provider.
     */
    public function syncRecording(VideoConferenceRoom $room): void
    {
        $adapter = $this->providerManager->resolve($room->provider);
        $info = $adapter->getRecording($room->external_room_id);

        $room->update([
            'recording_status' => $info->status,
            'recording_url' => $info->url,
            'recording_duration_seconds' => $info->durationSeconds,
            'recording_size_bytes' => $info->sizeBytes,
        ]);
    }
}
