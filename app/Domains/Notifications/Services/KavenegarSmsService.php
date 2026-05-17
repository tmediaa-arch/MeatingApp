<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * سرویس پیامک کاوه‌نگار — متد اعتبارسنجی (verify/lookup).
 *
 * این متد برای ارسال کد تأیید، رمز عبور و لینک‌های ضروری استفاده می‌شود؛
 * پیامک‌های آن بالاترین اولویت را دارند و فیلتر نمی‌شوند. نیازی به ارسال
 * فرستنده نیست — فقط گیرنده، مقدار token و نام الگو لازم است.
 *
 * مستندات: https://api.kavenegar.com/v1/{API-KEY}/verify/lookup.json
 */
class KavenegarSmsService
{
    /** نگاشت کدهای خطای کاوه‌نگار به پیام فارسی. */
    private const ERROR_MESSAGES = [
        400 => 'پارامترها ناقص است.',
        401 => 'حساب کاربری غیرفعال شده است.',
        402 => 'عملیات ناموفق بود.',
        403 => 'کد شناسایی (API-Key) نامعتبر است.',
        404 => 'متد نامشخص است.',
        405 => 'متد Get/Post اشتباه است.',
        406 => 'پارامتر اجباری خالی ارسال شده است.',
        407 => 'دسترسی به اطلاعات مورد نظر مجاز نیست.',
        409 => 'سرور قادر به پاسخ‌گویی نیست؛ بعداً تلاش کنید.',
        411 => 'دریافت‌کننده نامعتبر است.',
        412 => 'فرستنده نامعتبر است.',
        418 => 'اعتبار حساب شما کافی نیست.',
        422 => 'داده‌ها به دلیل وجود کاراکتر نامناسب قابل پردازش نیست.',
        424 => 'الگوی مورد نظر پیدا نشد یا هنوز تأیید نشده است.',
        426 => 'استفاده از این متد نیازمند سرویس پیشرفته است.',
        428 => 'ارسال کد از طریق تماس تلفنی امکان‌پذیر نیست.',
        431 => 'ساختار کد صحیح نیست (شامل فاصله، خط جدید یا جداکننده).',
        432 => 'پارامتر کد در متن الگو پیدا نشد.',
        451 => 'فراخوانی بیش از حد مجاز در بازهٔ زمانی.',
        607 => 'نام تگ انتخابی اشتباه است.',
    ];

    /**
     * ارسال پیامک اعتبارسنجی.
     *
     * @param  array<string,string>  $options  token2, token3, token10, token20, type, tag
     * @return array<string,mixed>  رکورد entries بازگشتی از کاوه‌نگار
     */
    public function sendVerify(string $receptor, string $token, string $template, array $options = []): array
    {
        $apiKey = (string) config('services.kavenegar.api_key');

        if ($apiKey === '') {
            throw new RuntimeException('KAVENEGAR_API_KEY تنظیم نشده است.');
        }

        $url = rtrim((string) config('services.kavenegar.base_url'), '/')
            . '/' . $apiKey . '/verify/lookup.json';

        $params = array_filter(array_merge([
            'receptor' => $receptor,
            'token' => $token,
            'template' => $template,
        ], $options), fn ($v) => $v !== null && $v !== '');

        $response = Http::timeout((int) config('services.kavenegar.timeout', 15))
            ->asForm()
            ->post($url, $params);

        $body = $response->json() ?? [];
        $status = (int) ($body['return']['status'] ?? $response->status());

        if ($status !== 200) {
            $message = self::ERROR_MESSAGES[$status]
                ?? ($body['return']['message'] ?? "خطای ناشناخته کاوه‌نگار (کد {$status})");

            Log::warning('Kavenegar verify failed', [
                'receptor' => $receptor,
                'template' => $template,
                'status' => $status,
                'message' => $message,
            ]);

            throw new RuntimeException("ارسال پیامک ناموفق بود: {$message}", $status);
        }

        return $body['entries'][0] ?? [];
    }
}
