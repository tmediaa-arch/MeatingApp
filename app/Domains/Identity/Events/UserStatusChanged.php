<?php

declare(strict_types=1);

namespace App\Domains\Identity\Events;

use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class UserStatusChanged
{
    use Dispatchable;

    public function __construct(
        public readonly User $user,
        public readonly UserStatus $previousStatus,
        public readonly UserStatus $newStatus,
        public readonly ?string $reason = null,
    ) {
    }
}
