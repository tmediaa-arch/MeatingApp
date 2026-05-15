<?php

declare(strict_types=1);

namespace App\Domains\VideoConference\DTOs;

/**
 * RecordingInfo — اطلاعات ضبط یک جلسه ویدئوکنفرانس.
 */
final class RecordingInfo
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $status,
        public readonly ?string $url = null,
        public readonly ?int $durationSeconds = null,
        public readonly ?int $sizeBytes = null,
        public readonly ?\DateTimeInterface $availableAt = null,
        public readonly array $metadata = [],
    ) {
    }
}
