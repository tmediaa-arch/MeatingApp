<?php

declare(strict_types=1);

namespace App\Domains\VideoConference\DTOs;

/**
 * RoomStatusInfo — وضعیت لحظه‌ای یک اتاق ویدئوکنفرانس.
 */
final class RoomStatusInfo
{
    /**
     * @param array<int, mixed> $participants
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly bool $isActive,
        public readonly int $participantCount = 0,
        public readonly ?\DateTimeInterface $startedAt = null,
        public readonly bool $isRecording = false,
        public readonly array $participants = [],
        public readonly array $metadata = [],
    ) {
    }
}
