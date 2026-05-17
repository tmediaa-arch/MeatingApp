<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Kavenegar SMS Gateway
    |--------------------------------------------------------------------------
    |
    | پیکربندی سرویس پیامک کاوه‌نگار. برای ارسال کد اعتبارسنجی (OTP) و لینک
    | دعوت از متد verify/lookup استفاده می‌شود. نام الگوها باید ابتدا در پنل
    | کاوه‌نگار تعریف و تأیید شده باشند.
    |
    */
    'kavenegar' => [
        'api_key' => env('KAVENEGAR_API_KEY'),
        'base_url' => env('KAVENEGAR_BASE_URL', 'https://api.kavenegar.com/v1'),
        'timeout' => (int) env('KAVENEGAR_TIMEOUT', 15),

        // نام الگوهای تأییدشده در پنل کاوه‌نگار
        'otp_template' => env('KAVENEGAR_OTP_TEMPLATE', 'otp-login'),
        'invite_template' => env('KAVENEGAR_INVITE_TEMPLATE', 'invite-link'),
    ],

    /*
    |--------------------------------------------------------------------------
    | OTP / Invitation behaviour
    |--------------------------------------------------------------------------
    */
    'otp' => [
        'length' => 5,                  // تعداد ارقام کد
        'ttl_seconds' => 120,           // مدت اعتبار کد
        'resend_cooldown_seconds' => 60, // فاصلهٔ مجاز بین دو ارسال
        'max_per_hour' => 6,            // سقف ارسال در هر ساعت برای هر شماره
        'max_attempts' => 5,            // سقف تلاش اشتباه برای هر کد
    ],

    'invitation' => [
        'ttl_days' => 7,                // مدت اعتبار لینک دعوت
        'default_role' => env('INVITATION_DEFAULT_ROLE', 'invitee'),
    ],

];
