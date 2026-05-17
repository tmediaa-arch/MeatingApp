<?php

declare(strict_types=1);

namespace App\Support;

/**
 * نرمال‌سازی شمارهٔ موبایل ایران به فرمت 09xxxxxxxxx.
 */
class Mobile
{
    /**
     * شمارهٔ ورودی را به فرمت استاندارد 09xxxxxxxxx تبدیل می‌کند.
     * در صورت نامعتبر بودن، null برمی‌گرداند.
     */
    public static function normalize(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        // ارقام فارسی/عربی را به انگلیسی تبدیل و غیررقم‌ها را حذف کن
        $fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $ar = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $digits = preg_replace('/\D/', '', str_replace([...$fa, ...$ar], range(0, 9), $raw)) ?? '';

        // 0098xxxxxxxxxx یا 98xxxxxxxxxx → 0xxxxxxxxxx
        if (str_starts_with($digits, '0098') && strlen($digits) === 14) {
            $digits = '0' . substr($digits, 4);
        } elseif (str_starts_with($digits, '98') && strlen($digits) === 12) {
            $digits = '0' . substr($digits, 2);
        } elseif (strlen($digits) === 10 && str_starts_with($digits, '9')) {
            $digits = '0' . $digits;
        }

        // باید دقیقاً 09xxxxxxxxx باشد
        if (strlen($digits) === 11 && str_starts_with($digits, '09')) {
            return $digits;
        }

        return null;
    }
}
