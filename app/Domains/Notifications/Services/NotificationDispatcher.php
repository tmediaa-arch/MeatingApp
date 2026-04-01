<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Services;

use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Enums\NotificationChannel;
use App\Domains\Notifications\Enums\NotificationStatus;
use App\Domains\Notifications\Exceptions\NotificationException;
use App\Domains\Notifications\Models\NotificationOutbox;
use App\Domains\Notifications\Models\NotificationPreference;
use App\Domains\Notifications\Models\NotificationTemplate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * NotificationDispatcher — هسته ارسال اعلان‌ها.
 *
 * مسئولیت‌ها:
 * 1. resolve کردن template
 * 2. اعمال user preferences (channel enabled/disabled + quiet hours)
 * 3. render کردن body/subject با متغیرها
 * 4. ایجاد NotificationOutbox records
 * 5. dispatch کردن Job ها برای ارسال واقعی
 *
 * این Service به‌جای ارسال مستقیم، رکوردهای صف می‌سازد
 * و Job ها ارسال واقعی را انجام می‌دهند تا retry و throttling ممکن باشد.
 */
class NotificationDispatcher
{
    /**
     * ارسال اعلان به یک کاربر.
     *
     * @param string $templateKey کلید قالب (مثلاً 'meeting.invitation')
     * @param User|int $recipient کاربر دریافت‌کننده
     * @param array $variables متغیرهای قالب
     * @param Model|null $notifiable مرجع به entity (meeting, task, etc.)
     * @param array $options
     *        - channels: array<string> override پیش‌فرض
     *        - scheduled_at: \DateTimeInterface
     *        - priority: string
     *        - correlation_id: string
     * @return array<int> شناسه‌های NotificationOutbox ایجاد شده
     */
    public function send(
        string $templateKey,
        User|int $recipient,
        array $variables = [],
        ?Model $notifiable = null,
        array $options = [],
    ): array {
        $user = $recipient instanceof User ? $recipient : User::findOrFail($recipient);

        // 1. resolve template
        $template = NotificationTemplate::active()->byKey($templateKey)->first();
        if (!$template) {
            throw NotificationException::templateNotFound($templateKey);
        }

        // 2. کانال‌های مجاز
        $allowedChannels = $options['channels']
            ?? $template->supported_channels
            ?? ['in_app'];

        $preferences = $this->getOrCreatePreferences($user, $templateKey);

        $createdIds = [];
        $correlationId = $options['correlation_id'] ?? (string) Str::uuid();
        $scheduledAt = $options['scheduled_at'] ?? null;
        $priority = $options['priority'] ?? $template->priority ?? 'normal';

        foreach ($allowedChannels as $channel) {
            $channelEnum = NotificationChannel::tryFrom($channel);
            if (!$channelEnum) continue;

            // 3. user preferences چک
            if (!$this->shouldSendOnChannel($preferences, $channel, $template)) {
                continue;
            }

            // 4. آدرس مقصد
            $toAddress = $this->resolveAddress($user, $channelEnum);
            if (!$toAddress) {
                Log::info("No address for {$user->id} on channel {$channel}");
                continue;
            }

            // 5. render content
            $channelContent = $template->getChannelContent($channel);
            if (!$channelContent) continue;

            $allVariables = array_merge($this->getDefaultVariables($user), $variables);

            // 6. در quiet hours؟ اگر in_app نباشد، schedule بزن
            $finalScheduledAt = $scheduledAt;
            if (!$scheduledAt
                && $channel !== 'in_app'
                && $preferences->isInQuietHours()
            ) {
                $finalScheduledAt = $this->nextQuietEnd($preferences);
            }

            // 7. ایجاد رکورد
            $outbox = NotificationOutbox::create([
                'correlation_id' => $correlationId,
                'template_id' => $template->id,
                'recipient_user_id' => $user->id,
                'recipient_employee_id' => $user->employee_id ?? null,
                'channel' => $channelEnum,
                'to_address' => $toAddress,
                'subject' => $channelContent->subject
                    ? $channelContent->render($allVariables, 'subject')
                    : null,
                'body' => $channelContent->render($allVariables, 'body'),
                'body_html' => $channelContent->body_html
                    ? $channelContent->render($allVariables, 'body_html')
                    : null,
                'notifiable_type' => $notifiable ? $notifiable->getMorphClass() : null,
                'notifiable_id' => $notifiable?->getKey(),
                'status' => NotificationStatus::Pending,
                'priority' => $priority,
                'scheduled_at' => $finalScheduledAt,
                'metadata' => [
                    'template_key' => $templateKey,
                    'variables' => $variables,
                ],
            ]);

            $createdIds[] = $outbox->id;

            // 8. dispatch Job اگر فوری
            // در فاز ۳: Job ها در فاز بعد فعال می‌شوند. در این مرحله فقط ایجاد می‌کنیم.
            // app(SendNotificationJob::class, ['notificationId' => $outbox->id])
            //     ->onQueue('notifications-' . $priority)
            //     ->delay($finalScheduledAt);
        }

        return $createdIds;
    }

    /**
     * ارسال bulk به چندین کاربر.
     */
    public function sendBulk(
        string $templateKey,
        array $userIds,
        array $variables = [],
        ?Model $notifiable = null,
        array $options = [],
    ): int {
        $correlationId = $options['correlation_id'] ?? (string) Str::uuid();
        $options['correlation_id'] = $correlationId;

        $count = 0;
        foreach ($userIds as $userId) {
            try {
                $ids = $this->send(
                    templateKey: $templateKey,
                    recipient: $userId,
                    variables: $variables,
                    notifiable: $notifiable,
                    options: $options,
                );
                $count += count($ids);
            } catch (\Throwable $e) {
                Log::warning("Failed to dispatch notification for user {$userId}: " . $e->getMessage());
            }
        }
        return $count;
    }

    /**
     * اعلان in_app فقط (سریع‌ترین مسیر)
     */
    public function inApp(User|int $recipient, string $title, string $body, ?Model $notifiable = null): NotificationOutbox
    {
        $user = $recipient instanceof User ? $recipient : User::findOrFail($recipient);

        return NotificationOutbox::create([
            'correlation_id' => (string) Str::uuid(),
            'recipient_user_id' => $user->id,
            'recipient_employee_id' => $user->employee_id ?? null,
            'channel' => NotificationChannel::InApp,
            'to_address' => (string) $user->id,
            'subject' => $title,
            'body' => $body,
            'notifiable_type' => $notifiable?->getMorphClass(),
            'notifiable_id' => $notifiable?->getKey(),
            'status' => NotificationStatus::Sent, // in_app immediately "sent"
            'sent_at' => now(),
        ]);
    }

    // ──────── Helpers ────────

    private function shouldSendOnChannel(NotificationPreference $prefs, string $channel, NotificationTemplate $template): bool
    {
        // قالب‌های غیرقابل غیرفعالی همیشه ارسال می‌شوند
        if (!$template->is_user_disablable) return true;

        return $prefs->isChannelEnabled($channel);
    }

    private function resolveAddress(User $user, NotificationChannel $channel): ?string
    {
        return match ($channel) {
            NotificationChannel::Email => $user->email,
            NotificationChannel::Sms => $user->mobile ?? $user->employee?->mobile,
            NotificationChannel::InApp,
            NotificationChannel::Push => (string) $user->id,
            NotificationChannel::Webhook => $user->webhook_url ?? null,
        };
    }

    private function getOrCreatePreferences(User $user, string $templateKey): NotificationPreference
    {
        return NotificationPreference::firstOrCreate(
            ['user_id' => $user->id, 'template_key' => $templateKey],
            [
                'email_enabled' => true,
                'sms_enabled' => true,
                'in_app_enabled' => true,
                'push_enabled' => true,
            ]
        );
    }

    private function getDefaultVariables(User $user): array
    {
        return [
            'user_name' => $user->name,
            'user_email' => $user->email,
            'organization_name' => $user->employee?->organization?->name ?? '',
            'date' => now()->format('Y/m/d'),
            'time' => now()->format('H:i'),
            'app_name' => config('app.name', 'MMS'),
            'app_url' => config('app.url', ''),
        ];
    }

    private function nextQuietEnd(NotificationPreference $prefs): \DateTimeInterface
    {
        if (!$prefs->quiet_hours_end) return now();
        $endTime = $prefs->quiet_hours_end->format('H:i');
        $next = now()->setTimeFromTimeString($endTime);
        if ($next->isPast()) {
            $next->addDay();
        }
        return $next;
    }
}
