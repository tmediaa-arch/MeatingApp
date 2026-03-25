<?php

declare(strict_types=1);

namespace App\Domains\VideoConference\Adapters;

use App\Domains\VideoConference\DTOs\CreateRoomRequest;
use App\Domains\VideoConference\DTOs\CreateRoomResponse;
use App\Domains\VideoConference\DTOs\HealthCheckResult;
use App\Domains\VideoConference\DTOs\JoinUrlRequest;
use App\Domains\VideoConference\DTOs\RoomStatusInfo;
use Illuminate\Support\Str;

/**
 * Null Provider:
 *
 * یک provider غیرعملی که فقط لینک‌های دستی را ذخیره می‌کند.
 * برای مواردی مفید است که سازمان از یک سرویس خارجی (مثلاً Zoom دستی)
 * استفاده می‌کند و فقط می‌خواهد لینک را به جلسه متصل کند.
 *
 * در این driver:
 *  - createRoom یک id تصادفی تولید می‌کند
 *  - URLها از config گرفته می‌شوند (manual_host_url / manual_attendee_url)
 *  - getRoomStatus همیشه inactive برمی‌گرداند
 */
class NullVideoConferenceProvider extends AbstractVideoConferenceProvider
{
    public function driverKey(): string
    {
        return 'null';
    }

    protected function requiredConfigKeys(): array
    {
        return []; // هیچ کلید الزامی ندارد
    }

    public function createRoom(CreateRoomRequest $request): CreateRoomResponse
    {
        $externalId = 'null-' . Str::uuid()->toString();

        // اگر در config لینک‌های دستی تنظیم شده باشد، از آنها استفاده می‌کنیم
        $hostUrl = $this->config['manual_host_url']
            ?? "https://example.invalid/null-vc/{$externalId}?role=host";
        $attendeeUrl = $this->config['manual_attendee_url']
            ?? "https://example.invalid/null-vc/{$externalId}?role=attendee";

        return new CreateRoomResponse(
            externalRoomId: $externalId,
            hostUrl: $hostUrl,
            attendeeUrl: $attendeeUrl,
            moderatorPassword: null,
            attendeePassword: null,
            providerMetadata: ['driver' => 'null', 'note' => 'Manual link registration'],
        );
    }

    public function generateJoinUrl(JoinUrlRequest $request): string
    {
        $base = $this->config['manual_attendee_url']
            ?? "https://example.invalid/null-vc/{$request->externalRoomId}";

        // append display name as a query parameter
        $separator = str_contains($base, '?') ? '&' : '?';
        return $base . $separator . 'name=' . urlencode($request->displayName);
    }

    public function getRoomStatus(string $externalRoomId): RoomStatusInfo
    {
        // null provider هیچ اطلاعات لحظه‌ای ندارد
        return new RoomStatusInfo(isActive: false, participantCount: 0);
    }

    public function endRoom(string $externalRoomId): void
    {
        // no-op
    }

    public function checkHealth(): HealthCheckResult
    {
        // null provider همیشه healthy است
        return HealthCheckResult::healthy(0);
    }
}
