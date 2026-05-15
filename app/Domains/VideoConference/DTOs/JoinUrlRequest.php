<?php

declare(strict_types=1);

namespace App\Domains\VideoConference\DTOs;

use App\Domains\VideoConference\Enums\AttendanceRole;

/**
 * JoinUrlRequest — درخواست تولید لینک ورود به اتاق ویدئوکنفرانس.
 */
final class JoinUrlRequest
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $externalRoomId,
        public readonly string $displayName,
        public readonly AttendanceRole $role,
        public readonly ?string $email = null,
        public readonly ?int $userId = null,
        public readonly array $metadata = [],
    ) {
    }
}
