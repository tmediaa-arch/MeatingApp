<?php

declare(strict_types=1);

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Models\OtpCode;
use App\Domains\Notifications\Jobs\SendSmsJob;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * تولید، ارسال و بررسی کدهای یک‌بارمصرف اعتبارسنجی (OTP).
 */
class OtpService
{
    /**
     * تولید کد، ذخیرهٔ hash آن و ارسال پیامک از طریق کاوه‌نگار.
     *
     * @throws RuntimeException در صورت عبور از محدودیت‌های ارسال
     */
    public function send(string $mobile, ?int $userId = null, ?string $ip = null, string $purpose = 'login'): void
    {
        $this->assertWithinRateLimits($mobile, $purpose);

        $code = $this->generateNumericCode((int) config('services.otp.length', 5));

        OtpCode::create([
            'mobile' => $mobile,
            'code_hash' => Hash::make($code),
            'purpose' => $purpose,
            'user_id' => $userId,
            'ip_address' => $ip,
            'expires_at' => now()->addSeconds((int) config('services.otp.ttl_seconds', 120)),
        ]);

        SendSmsJob::dispatch(
            $mobile,
            $code,
            (string) config('services.kavenegar.otp_template'),
        );
    }

    /**
     * بررسی کد واردشده. در صورت صحت، کد مصرف‌شده علامت می‌خورد.
     */
    public function verify(string $mobile, string $code, string $purpose = 'login'): bool
    {
        $otp = OtpCode::query()
            ->forLogin($mobile, $purpose)
            ->usable()
            ->latest('id')
            ->first();

        if ($otp === null) {
            return false;
        }

        // سقف تلاش اشتباه — کد را می‌سوزاند
        if ($otp->attempts >= (int) config('services.otp.max_attempts', 5)) {
            $otp->update(['consumed_at' => now()]);

            return false;
        }

        if (! Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');

            return false;
        }

        $otp->update(['consumed_at' => now()]);

        return true;
    }

    private function assertWithinRateLimits(string $mobile, string $purpose): void
    {
        $cooldown = (int) config('services.otp.resend_cooldown_seconds', 60);

        $recent = OtpCode::query()
            ->forLogin($mobile, $purpose)
            ->where('created_at', '>', now()->subSeconds($cooldown))
            ->exists();

        if ($recent) {
            throw new RuntimeException("برای ارسال مجدد کد، {$cooldown} ثانیه صبر کنید.");
        }

        $hourCount = OtpCode::query()
            ->forLogin($mobile, $purpose)
            ->where('created_at', '>', now()->subHour())
            ->count();

        if ($hourCount >= (int) config('services.otp.max_per_hour', 6)) {
            throw new RuntimeException('تعداد درخواست کد در یک ساعت اخیر بیش از حد مجاز است.');
        }
    }

    private function generateNumericCode(int $length): string
    {
        $length = max(4, min(8, $length));
        $min = (int) str_pad('1', $length, '0');
        $max = (int) str_pad('', $length, '9');

        return (string) random_int($min, $max);
    }
}
