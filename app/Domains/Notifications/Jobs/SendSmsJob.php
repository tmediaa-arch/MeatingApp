<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Jobs;

use App\Domains\Notifications\Services\KavenegarSmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * ارسال پیامک اعتبارسنجی کاوه‌نگار به‌صورت صف‌بندی‌شده.
 *
 * با QUEUE_CONNECTION=sync بلافاصله اجرا می‌شود؛ با database/redis توسط
 * worker پردازش می‌شود (به docs/راهنمای-اجرای-پیامک.md مراجعه کنید).
 */
class SendSmsJob implements ShouldQueue
{
    use Queueable;
    use InteractsWithQueue;
    use SerializesModels;

    /** تعداد تلاش مجدد در صورت خطا. */
    public int $tries = 3;

    /** فاصلهٔ تلاش‌های مجدد (ثانیه). */
    public array $backoff = [15, 60, 180];

    /**
     * @param  array<string,string>  $options
     */
    public function __construct(
        public string $receptor,
        public string $token,
        public string $template,
        public array $options = [],
    ) {
    }

    public function handle(KavenegarSmsService $sms): void
    {
        $sms->sendVerify($this->receptor, $this->token, $this->template, $this->options);
    }
}
