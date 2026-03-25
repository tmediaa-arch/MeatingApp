<?php

declare(strict_types=1);

namespace App\Domains\VideoConference\Adapters;

use App\Domains\VideoConference\DTOs\CreateRoomRequest;
use App\Domains\VideoConference\DTOs\CreateRoomResponse;
use App\Domains\VideoConference\DTOs\HealthCheckResult;
use App\Domains\VideoConference\DTOs\JoinUrlRequest;
use App\Domains\VideoConference\DTOs\RecordingInfo;
use App\Domains\VideoConference\DTOs\RoomStatusInfo;
use App\Domains\VideoConference\Enums\AttendanceRole;
use App\Domains\VideoConference\Exceptions\VideoConferenceException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Jitsi Meet Adapter.
 *
 * Jitsi مدل بسیار سبک‌تری دارد:
 *  - اتاق با URL مستقیم ساخته می‌شود (نیاز به API call نیست برای create)
 *  - برای auth از JWT token استفاده می‌شود
 *
 * Config مورد نیاز:
 *  - base_url       — مثل https://meet.example.com
 *  - jwt_secret     — برای signing tokenها
 *  - jwt_app_id     — application id
 *  - jaas_management_url (اختیاری) — برای دریافت ضبط
 */
class JitsiVideoConferenceProvider extends AbstractVideoConferenceProvider
{
    public function driverKey(): string
    {
        return 'jitsi';
    }

    protected function requiredConfigKeys(): array
    {
        return ['base_url', 'jwt_secret', 'jwt_app_id'];
    }

    public function supportsRecording(): bool
    {
        return true; // با Jibri
    }

    public function supportsBreakoutRooms(): bool
    {
        return true;
    }

    public function createRoom(CreateRoomRequest $request): CreateRoomResponse
    {
        $this->ensureConfigured();

        $roomName = $this->sanitizeRoomName($request->subject) . '-' . substr($request->internalRoomId, 0, 8);

        // برای host: token با moderator role
        $hostToken = $this->generateJwt($roomName, 'host', 'Meeting Host');
        $hostUrl = "{$this->config['base_url']}/{$roomName}?jwt={$hostToken}";

        // برای attendee: token عمومی (هنگام تولید واقعی برای هر کاربر، با generateJoinUrl)
        $attendeeUrl = "{$this->config['base_url']}/{$roomName}";

        return new CreateRoomResponse(
            externalRoomId: $roomName,
            hostUrl: $hostUrl,
            attendeeUrl: $attendeeUrl,
            providerMetadata: ['driver' => 'jitsi', 'room_name' => $roomName],
        );
    }

    public function generateJoinUrl(JoinUrlRequest $request): string
    {
        $this->ensureConfigured();

        $jwt = $this->generateJwt(
            roomName: $request->externalRoomId,
            role: $request->role->value,
            displayName: $request->displayName,
            email: $request->email,
        );

        return "{$this->config['base_url']}/{$request->externalRoomId}?jwt={$jwt}";
    }

    public function getRoomStatus(string $externalRoomId): RoomStatusInfo
    {
        // Jitsi public API محدودی دارد — اگر management endpoint تنظیم شده باشد، استفاده می‌کنیم
        if (!isset($this->config['management_url'])) {
            return new RoomStatusInfo(isActive: false, participantCount: 0);
        }

        try {
            $response = Http::timeout(5)
                ->withToken($this->config['management_token'] ?? '')
                ->get("{$this->config['management_url']}/rooms/{$externalRoomId}");

            if (!$response->ok()) {
                return new RoomStatusInfo(isActive: false, participantCount: 0);
            }

            $data = $response->json();
            return new RoomStatusInfo(
                isActive: (bool) ($data['active'] ?? false),
                participantCount: (int) ($data['participant_count'] ?? 0),
                isRecording: (bool) ($data['recording'] ?? false),
                participants: $data['participants'] ?? [],
                metadata: $data,
            );
        } catch (\Throwable $e) {
            return new RoomStatusInfo(isActive: false, participantCount: 0);
        }
    }

    public function endRoom(string $externalRoomId): void
    {
        if (!isset($this->config['management_url'])) {
            return;
        }
        try {
            Http::timeout(5)
                ->withToken($this->config['management_token'] ?? '')
                ->delete("{$this->config['management_url']}/rooms/{$externalRoomId}");
        } catch (\Throwable) {
            // silently ignore
        }
    }

    protected function doStartRecording(string $externalRoomId): void
    {
        if (!isset($this->config['management_url'])) {
            throw VideoConferenceException::externalApiFailed('management_url پیکربندی نشده است.');
        }
        Http::timeout(10)
            ->withToken($this->config['management_token'] ?? '')
            ->post("{$this->config['management_url']}/rooms/{$externalRoomId}/recording/start");
    }

    protected function doStopRecording(string $externalRoomId): void
    {
        if (!isset($this->config['management_url'])) return;
        Http::timeout(10)
            ->withToken($this->config['management_token'] ?? '')
            ->post("{$this->config['management_url']}/rooms/{$externalRoomId}/recording/stop");
    }

    protected function doGetRecording(string $externalRoomId): RecordingInfo
    {
        if (!isset($this->config['management_url'])) {
            return new RecordingInfo(status: 'not_supported');
        }
        try {
            $response = Http::timeout(10)
                ->withToken($this->config['management_token'] ?? '')
                ->get("{$this->config['management_url']}/rooms/{$externalRoomId}/recording");

            if (!$response->ok()) {
                return new RecordingInfo(status: 'not_recording');
            }
            $data = $response->json();
            return new RecordingInfo(
                status: $data['status'] ?? 'not_recording',
                url: $data['url'] ?? null,
                durationSeconds: $data['duration_seconds'] ?? null,
                sizeBytes: $data['size_bytes'] ?? null,
                metadata: $data,
            );
        } catch (\Throwable $e) {
            return new RecordingInfo(status: 'failed', metadata: ['error' => $e->getMessage()]);
        }
    }

    public function checkHealth(): HealthCheckResult
    {
        try {
            $start = microtime(true);
            $response = Http::timeout(5)->get($this->config['base_url'] ?? 'about:blank');
            $elapsed = (int) ((microtime(true) - $start) * 1000);

            if ($response->ok()) {
                return HealthCheckResult::healthy($elapsed);
            }
            return HealthCheckResult::unhealthy("HTTP {$response->status()}");
        } catch (\Throwable $e) {
            return HealthCheckResult::unhealthy($e->getMessage());
        }
    }

    // ─────────── Private helpers ───────────

    private function sanitizeRoomName(string $name): string
    {
        // Jitsi نام اتاق را در URL استفاده می‌کند — باید safe باشد
        $clean = preg_replace('/[^a-zA-Z0-9-_]/', '', Str::ascii($name));
        return strtolower(substr($clean, 0, 50)) ?: 'meeting';
    }

    /**
     * تولید JWT برای Jitsi authentication.
     *
     * شرح: ساده‌ترین HS256 JWT بدون نیاز به firebase/php-jwt.
     * در production توصیه می‌شود از firebase/php-jwt استفاده شود.
     */
    private function generateJwt(
        string $roomName,
        string $role,
        string $displayName,
        ?string $email = null,
    ): string {
        $header = [
            'alg' => 'HS256',
            'typ' => 'JWT',
        ];

        $payload = [
            'aud' => 'jitsi',
            'iss' => $this->config['jwt_app_id'],
            'sub' => parse_url($this->config['base_url'], PHP_URL_HOST),
            'room' => $roomName,
            'exp' => time() + 3600 * 4, // معتبر برای ۴ ساعت
            'context' => [
                'user' => [
                    'name' => $displayName,
                    'email' => $email,
                    'moderator' => in_array($role, ['host', 'moderator'], true),
                ],
            ],
        ];

        $headerB64 = $this->base64UrlEncode(json_encode($header, JSON_UNESCAPED_UNICODE));
        $payloadB64 = $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_UNICODE));
        $signature = hash_hmac('sha256', "{$headerB64}.{$payloadB64}", $this->config['jwt_secret'], true);
        $signatureB64 = $this->base64UrlEncode($signature);

        return "{$headerB64}.{$payloadB64}.{$signatureB64}";
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
