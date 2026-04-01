<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Jobs;

use App\Domains\Notifications\Enums\NotificationChannel;
use App\Domains\Notifications\Enums\NotificationStatus;
use App\Domains\Notifications\Models\NotificationOutbox;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Job ارسال یک اعلان از طریق channel مناسب.
 *
 * این Job:
 * 1. NotificationOutbox را load می‌کند
 * 2. بسته به channel، ارسال واقعی انجام می‌دهد
 * 3. status را به‌روز می‌کند
 * 4. در صورت شکست، next_retry_at تنظیم می‌کند
 */
class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1; // ما خودمان retry مدیریت می‌کنیم
    public int $timeout = 60;

    public function __construct(
        public readonly int $notificationId,
    ) {
    }

    public function handle(): void
    {
        $notification = NotificationOutbox::find($this->notificationId);
        if (!$notification) return;

        // اگر دیگر pending نیست، skip
        if ($notification->status !== NotificationStatus::Pending) return;

        // اگر برای زمان آینده schedule شده، dispatch مجدد
        if ($notification->scheduled_at && $notification->scheduled_at->isFuture()) {
            self::dispatch($notification->id)->delay($notification->scheduled_at);
            return;
        }

        try {
            match ($notification->channel) {
                NotificationChannel::Email => $this->sendEmail($notification),
                NotificationChannel::Sms => $this->sendSms($notification),
                NotificationChannel::InApp => $this->sendInApp($notification),
                NotificationChannel::Push => $this->sendPush($notification),
                NotificationChannel::Webhook => $this->sendWebhook($notification),
            };

            $notification->markAsSent();
        } catch (\Throwable $e) {
            Log::error("Notification send failed for #{$notification->id}: " . $e->getMessage(), [
                'channel' => $notification->channel->value,
                'recipient' => $notification->to_address,
            ]);
            $notification->markAsFailed($e->getMessage());
        }
    }

    // ──────── کانال‌ها ────────

    private function sendEmail(NotificationOutbox $notification): void
    {
        Mail::raw(
            $notification->body,
            function ($message) use ($notification) {
                $message
                    ->to(new Address($notification->to_address))
                    ->subject($notification->subject ?? '(بدون موضوع)');

                if ($notification->body_html) {
                    // در محیط واقعی از Mailable کلاس استفاده شود
                    $message->html($notification->body_html);
                }
            },
        );
    }

    private function sendSms(NotificationOutbox $notification): void
    {
        // در فاز ۳ stub فقط
        // در production از یک ارائه‌دهنده SMS ایرانی مثل کاوه‌نگار/فراز SMS استفاده شود
        $providerEndpoint = config('services.sms.endpoint');
        if (!$providerEndpoint) {
            Log::warning('SMS provider not configured; skipping SMS send');
            $notification->update([
                'provider_response' => ['note' => 'SMS provider not configured — stub'],
            ]);
            return;
        }

        // Http::post($providerEndpoint, [...]);
    }

    private function sendInApp(NotificationOutbox $notification): void
    {
        // در in_app نیازی به ارسال خارجی نیست؛
        // فقط رکورد را sent علامت می‌زنیم (در کارتابل قابل دیدن است).
        // اگر WebSocket/Pusher لازم باشد، اینجا dispatch event می‌شود.
    }

    private function sendPush(NotificationOutbox $notification): void
    {
        // FCM / OneSignal — در فاز ۴ کامل می‌شود
        Log::info("Push notification stub for #{$notification->id}");
    }

    private function sendWebhook(NotificationOutbox $notification): void
    {
        if (!$notification->to_address) {
            throw new \DomainException('Webhook URL خالی است.');
        }
        // Http::post($notification->to_address, [...]);
    }
}
