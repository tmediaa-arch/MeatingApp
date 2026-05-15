<?php

declare(strict_types=1);

namespace App\Domains\Meetings\Events;

use App\Domains\Meetings\Models\Meeting;
use Illuminate\Foundation\Events\Dispatchable;

class MeetingRescheduled
{
    use Dispatchable;

    public function __construct(
        public readonly Meeting $meeting,
        public readonly mixed $previousStart = null,
        public readonly mixed $previousEnd = null,
        public readonly mixed $newStart = null,
        public readonly mixed $newEnd = null,
        public readonly ?string $reason = null,
    ) {
    }
}
