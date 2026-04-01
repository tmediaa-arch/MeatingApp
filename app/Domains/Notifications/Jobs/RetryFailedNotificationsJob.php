<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Jobs;

use App\Domains\Notifications\Enums\NotificationStatus;
use App\Domains\Notifications\Models\NotificationOutbox;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RetryFailedNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public function handle(): void
    {
        $retryable = NotificationOutbox::query()->retryable()->limit(100)->get();

        foreach ($retryable as $notification) {
            // بازگشت به Pending و dispatch مجدد
            $notification->update([
                'status' => NotificationStatus::Pending,
                'next_retry_at' => null,
            ]);

            SendNotificationJob::dispatch($notification->id)
                ->onQueue('notifications-' . $notification->priority);
        }
    }
}
