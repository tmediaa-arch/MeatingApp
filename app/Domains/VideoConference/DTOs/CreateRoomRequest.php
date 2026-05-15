<?php

declare(strict_types=1);

namespace App\Domains\VideoConference\DTOs;

/**
 * CreateRoomRequest — درخواست ساخت اتاق ویدئوکنفرانس در provider خارجی.
 */
final class CreateRoomRequest
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $subject,
        public readonly string $internalRoomId,
        public readonly ?\DateTimeInterface $scheduledStartAt = null,
        public readonly ?\DateTimeInterface $scheduledEndAt = null,
        public readonly ?int $maxParticipants = null,
        public readonly bool $requirePassword = false,
        public readonly bool $waitingRoomEnabled = false,
        public readonly bool $recordingEnabled = false,
        public readonly ?string $welcomeMessage = null,
        public readonly array $metadata = [],
    ) {
    }
}
