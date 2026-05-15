<?php

declare(strict_types=1);

namespace App\Domains\VideoConference\DTOs;

/**
 * CreateRoomResponse — پاسخ provider پس از ساخت اتاق ویدئوکنفرانس.
 */
final class CreateRoomResponse
{
    /**
     * @param array<string, mixed> $providerMetadata
     */
    public function __construct(
        public readonly string $externalRoomId,
        public readonly string $hostUrl,
        public readonly string $attendeeUrl,
        public readonly ?string $moderatorPassword = null,
        public readonly ?string $attendeePassword = null,
        public readonly array $providerMetadata = [],
    ) {
    }
}
