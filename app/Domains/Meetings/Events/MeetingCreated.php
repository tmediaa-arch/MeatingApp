<?php

declare(strict_types=1);

namespace App\Domains\Meetings\Events;

use App\Domains\Identity\Models\User;
use App\Domains\Meetings\Models\Meeting;
use Illuminate\Foundation\Events\Dispatchable;

class MeetingCreated
{
    use Dispatchable;

    public function __construct(
        public readonly Meeting $meeting,
        public readonly ?User $createdBy = null,
    ) {
    }
}
