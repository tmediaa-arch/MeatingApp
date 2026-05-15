<?php

declare(strict_types=1);

namespace App\Domains\VideoConference\Contracts;

use App\Domains\VideoConference\DTOs\CreateRoomRequest;
use App\Domains\VideoConference\DTOs\CreateRoomResponse;
use App\Domains\VideoConference\DTOs\HealthCheckResult;
use App\Domains\VideoConference\DTOs\JoinUrlRequest;
use App\Domains\VideoConference\DTOs\RecordingInfo;
use App\Domains\VideoConference\DTOs\RoomStatusInfo;

/**
 * VideoConferenceProviderInterface — قرارداد همه adapter های ویدئوکنفرانس.
 */
interface VideoConferenceProviderInterface
{
    /**
     * کلید driver (alocom, jitsi, bigbluebutton, null).
     */
    public function driverKey(): string;

    /**
     * پیکربندی adapter قبل از استفاده.
     *
     * @param array<string, mixed> $config
     */
    public function configure(array $config): void;

    /**
     * ساخت اتاق ویدئوکنفرانس در provider.
     */
    public function createRoom(CreateRoomRequest $request): CreateRoomResponse;

    /**
     * تولید لینک ورود به اتاق برای یک شرکت‌کننده.
     */
    public function generateJoinUrl(JoinUrlRequest $request): string;

    /**
     * وضعیت لحظه‌ای اتاق.
     */
    public function getRoomStatus(string $externalRoomId): RoomStatusInfo;

    /**
     * پایان دادن به اتاق.
     */
    public function endRoom(string $externalRoomId): void;

    /**
     * بررسی سلامت provider.
     */
    public function checkHealth(): HealthCheckResult;

    /**
     * آیا provider از ضبط پشتیبانی می‌کند؟
     */
    public function supportsRecording(): bool;

    /**
     * آیا provider از breakout room پشتیبانی می‌کند؟
     */
    public function supportsBreakoutRooms(): bool;

    /**
     * شروع ضبط.
     */
    public function startRecording(string $externalRoomId): void;

    /**
     * توقف ضبط.
     */
    public function stopRecording(string $externalRoomId): void;

    /**
     * دریافت اطلاعات ضبط.
     */
    public function getRecording(string $externalRoomId): RecordingInfo;
}
