<?php

declare(strict_types=1);

namespace App\Domains\VideoConference\Adapters;

use App\Domains\VideoConference\DTOs\CreateRoomRequest;
use App\Domains\VideoConference\DTOs\CreateRoomResponse;
use App\Domains\VideoConference\DTOs\HealthCheckResult;
use App\Domains\VideoConference\DTOs\JoinUrlRequest;
use App\Domains\VideoConference\DTOs\RecordingInfo;
use App\Domains\VideoConference\DTOs\RoomStatusInfo;
use App\Domains\VideoConference\Exceptions\VideoConferenceException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Alocom Adapter.
 *
 * Alocom یک ارائه‌دهنده داخلی است که در سامانه‌های دولتی/بانکی ایران پرکاربرد است.
 *
 * توجه: structure دقیق API ممکن است تغییر کند — این پیاده‌سازی نمونه است
 * و باید بر اساس مستندات روز Alocom تنظیم شود.
 *
 * Config مورد نیاز:
 *  - api_base_url   — مثل https://alocom.example.com/api/v1
 *  - api_token      — توکن احراز هویت
 *  - tenant_id      — شناسه سازمان در Alocom
 */
class AlocomVideoConferenceProvider extends AbstractVideoConferenceProvider
{
    public function driverKey(): string
    {
        return 'alocom';
    }

    protected function requiredConfigKeys(): array
    {
        return ['api_base_url', 'api_token', 'tenant_id'];
    }

    public function supportsRecording(): bool
    {
        return true;
    }

    public function createRoom(CreateRoomRequest $request): CreateRoomResponse
    {
        $this->ensureConfigured();

        $payload = [
            'tenant_id' => $this->config['tenant_id'],
            'subject' => $request->subject,
            'external_ref' => $request->internalRoomId,
            'scheduled_start_at' => $request->scheduledStartAt?->format('c'),
            'scheduled_end_at' => $request->scheduledEndAt?->format('c'),
            'max_participants' => $request->maxParticipants,
            'require_password' => $request->requirePassword,
            'waiting_room' => $request->waitingRoomEnabled,
            'recording_enabled' => $request->recordingEnabled,
        ];

        try {
            $response = $this->httpClient()->post('/rooms', $payload);

            if (!$response->successful()) {
                throw VideoConferenceException::externalApiFailed(
                    "Alocom HTTP {$response->status()}: " . $response->body(),
                );
            }

            $data = $response->json();
            return new CreateRoomResponse(
                externalRoomId: $data['room_id'] ?? Str::uuid()->toString(),
                hostUrl: $data['host_url'],
                attendeeUrl: $data['attendee_url'],
                moderatorPassword: $data['moderator_password'] ?? null,
                attendeePassword: $data['attendee_password'] ?? null,
                providerMetadata: $data,
            );
        } catch (VideoConferenceException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw VideoConferenceException::externalApiFailed($e->getMessage());
        }
    }

    public function generateJoinUrl(JoinUrlRequest $request): string
    {
        $this->ensureConfigured();

        try {
            $response = $this->httpClient()->post(
                "/rooms/{$request->externalRoomId}/join-link",
                [
                    'display_name' => $request->displayName,
                    'role' => $request->role->value,
                    'email' => $request->email,
                    'user_id' => $request->userId,
                ],
            );

            if (!$response->successful()) {
                throw VideoConferenceException::externalApiFailed(
                    "Alocom join-link HTTP {$response->status()}",
                );
            }

            return $response->json('join_url');
        } catch (VideoConferenceException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw VideoConferenceException::externalApiFailed($e->getMessage());
        }
    }

    public function getRoomStatus(string $externalRoomId): RoomStatusInfo
    {
        try {
            $response = $this->httpClient()->get("/rooms/{$externalRoomId}");
            if (!$response->successful()) {
                return new RoomStatusInfo(isActive: false, participantCount: 0);
            }

            $data = $response->json();
            $startedAt = isset($data['started_at'])
                ? new \DateTimeImmutable($data['started_at'])
                : null;

            return new RoomStatusInfo(
                isActive: (bool) ($data['is_active'] ?? false),
                participantCount: (int) ($data['participant_count'] ?? 0),
                startedAt: $startedAt,
                isRecording: (bool) ($data['is_recording'] ?? false),
                participants: $data['participants'] ?? [],
                metadata: $data,
            );
        } catch (\Throwable) {
            return new RoomStatusInfo(isActive: false, participantCount: 0);
        }
    }

    public function endRoom(string $externalRoomId): void
    {
        try {
            $this->httpClient()->delete("/rooms/{$externalRoomId}");
        } catch (\Throwable) {
            // silently ignore — اتاق ممکن است قبلاً بسته شده باشد
        }
    }

    protected function doStartRecording(string $externalRoomId): void
    {
        $response = $this->httpClient()->post("/rooms/{$externalRoomId}/recording/start");
        if (!$response->successful()) {
            throw VideoConferenceException::externalApiFailed('Recording start failed');
        }
    }

    protected function doStopRecording(string $externalRoomId): void
    {
        $this->httpClient()->post("/rooms/{$externalRoomId}/recording/stop");
    }

    protected function doGetRecording(string $externalRoomId): RecordingInfo
    {
        try {
            $response = $this->httpClient()->get("/rooms/{$externalRoomId}/recording");
            if (!$response->successful()) {
                return new RecordingInfo(status: 'not_recording');
            }

            $data = $response->json();
            return new RecordingInfo(
                status: $data['status'] ?? 'not_recording',
                url: $data['url'] ?? null,
                durationSeconds: $data['duration_seconds'] ?? null,
                sizeBytes: $data['size_bytes'] ?? null,
                availableAt: isset($data['available_at']) ? new \DateTimeImmutable($data['available_at']) : null,
                metadata: $data,
            );
        } catch (\Throwable $e) {
            return new RecordingInfo(status: 'failed');
        }
    }

    public function checkHealth(): HealthCheckResult
    {
        try {
            $start = microtime(true);
            $response = $this->httpClient()->get('/health');
            $elapsed = (int) ((microtime(true) - $start) * 1000);

            if ($response->successful()) {
                return HealthCheckResult::healthy($elapsed);
            }
            return HealthCheckResult::unhealthy("HTTP {$response->status()}");
        } catch (\Throwable $e) {
            return HealthCheckResult::unhealthy($e->getMessage());
        }
    }

    private function httpClient()
    {
        return Http::baseUrl($this->config['api_base_url'])
            ->timeout(10)
            ->withToken($this->config['api_token'])
            ->acceptJson()
            ->asJson();
    }
}
