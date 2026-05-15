<?php

declare(strict_types=1);

namespace App\Domains\Meetings\Events;

use App\Domains\Meetings\Enums\MeetingStatus;
use App\Domains\Meetings\Models\Meeting;
use Illuminate\Foundation\Events\Dispatchable;

class MeetingStatusChanged
{
    use Dispatchable;

    public function __construct(
        public readonly Meeting $meeting,
        public readonly MeetingStatus $previousStatus,
        public readonly MeetingStatus $newStatus,
        public readonly ?string $reason = null,
    ) {
    }
}
