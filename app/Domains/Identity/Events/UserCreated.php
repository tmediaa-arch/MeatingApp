<?php

declare(strict_types=1);

namespace App\Domains\Identity\Events;

use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class UserCreated
{
    use Dispatchable;

    public function __construct(
        public readonly User $user,
    ) {
    }
}
