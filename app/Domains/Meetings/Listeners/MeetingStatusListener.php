<?php

declare(strict_types=1);

namespace App\Domains\Meetings\Listeners;

use App\Domains\Meetings\Events\MeetingStatusChanged;
use Illuminate\Support\Facades\Log;

/**
 * MeetingStatusListener — واکنش به تغییر وضعیت جلسه.
 *
 * در این فاز فقط تغییر را لاگ می‌کند. منطق‌های جانبی (اعلان، همگام‌سازی
 * تقویم، و ...) در فازهای بعدی به این listener افزوده می‌شوند.
 */
class MeetingStatusListener
{
    public function handle(MeetingStatusChanged $event): void
    {
        Log::info('Meeting status changed', [
            'meeting_id' => $event->meeting->id,
            'meeting_number' => $event->meeting->meeting_number,
            'from' => $event->previousStatus->value,
            'to' => $event->newStatus->value,
            'reason' => $event->reason,
        ]);
    }
}
