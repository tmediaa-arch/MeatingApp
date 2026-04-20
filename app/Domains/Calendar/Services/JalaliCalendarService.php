<?php

declare(strict_types=1);

namespace App\Domains\Calendar\Services;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Morilog\Jalali\CalendarUtils;
use Morilog\Jalali\Jalalian;

/**
 * سرویس تبدیل و فرمت‌بندی تاریخ شمسی.
 *
 * نکات کلیدی:
 * - در دیتابیس همه چیز Gregorian است (UTC یا Asia/Tehran)
 * - در UI نمایش شمسی است
 * - برای آرگومان‌های ورودی، اگر کاربر تاریخ شمسی وارد کرد، اول به میلادی تبدیل می‌کنیم
 * - این Service singleton است (در DomainServiceProvider ثبت می‌شود)
 *
 * این Service وابسته به morilog/jalali است.
 */
class JalaliCalendarService
{
    /**
     * تبدیل تاریخ شمسی به میلادی
     * فرمت ورودی پشتیبانی: "1403/01/15", "1403-01-15", "1403/01/15 14:30", "1403-01-15T14:30:00"
     */
    public function jalaliToGregorian(string $jalaliDateTime): CarbonImmutable
    {
        // جداسازی تاریخ و زمان
        $jalaliDateTime = trim($jalaliDateTime);
        $jalaliDateTime = str_replace(['T', '_'], ' ', $jalaliDateTime);

        if (str_contains($jalaliDateTime, ' ')) {
            [$datePart, $timePart] = explode(' ', $jalaliDateTime, 2);
        } else {
            $datePart = $jalaliDateTime;
            $timePart = '00:00:00';
        }

        // نرمال‌سازی separator
        $datePart = str_replace(['/', '-'], '/', $datePart);
        $parts = explode('/', $datePart);

        if (count($parts) !== 3) {
            throw new \InvalidArgumentException("Invalid Jalali date format: {$jalaliDateTime}");
        }

        [$jy, $jm, $jd] = array_map('intval', $parts);

        // تبدیل به میلادی
        [$gy, $gm, $gd] = CalendarUtils::toGregorian($jy, $jm, $jd);

        // اضافه کردن زمان
        $timeParts = explode(':', trim($timePart));
        $hour = (int) ($timeParts[0] ?? 0);
        $minute = (int) ($timeParts[1] ?? 0);
        $second = (int) ($timeParts[2] ?? 0);

        return CarbonImmutable::create($gy, $gm, $gd, $hour, $minute, $second, config('app.timezone', 'Asia/Tehran'));
    }

    /**
     * تبدیل میلادی به شمسی
     */
    public function gregorianToJalali(\DateTimeInterface $date, string $format = 'Y/m/d H:i'): string
    {
        return Jalalian::fromDateTime($date)->format($format);
    }

    /**
     * فرمت تاریخ شمسی کوتاه (فقط تاریخ): 1403/01/15
     */
    public function formatDate(\DateTimeInterface $date): string
    {
        return $this->gregorianToJalali($date, 'Y/m/d');
    }

    /**
     * فرمت تاریخ و زمان شمسی: 1403/01/15 14:30
     */
    public function formatDateTime(\DateTimeInterface $date): string
    {
        return $this->gregorianToJalali($date, 'Y/m/d H:i');
    }

    /**
     * فرمت متنی فارسی: ۱۵ فروردین ۱۴۰۳، ساعت ۱۴:۳۰
     */
    public function formatHuman(\DateTimeInterface $date): string
    {
        $jalali = Jalalian::fromDateTime($date);
        return $jalali->format('j F Y، ساعت H:i');
    }

    /**
     * تبدیل اعداد انگلیسی به فارسی
     */
    public function toFarsiDigits(string $text): string
    {
        $en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        return str_replace($en, $fa, $text);
    }

    /**
     * تبدیل اعداد فارسی/عربی به انگلیسی
     */
    public function toEnglishDigits(string $text): string
    {
        $fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $ar = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $text = str_replace($fa, $en, $text);
        $text = str_replace($ar, $en, $text);
        return $text;
    }

    /**
     * مرز شروع و پایان یک ماه شمسی (به میلادی)
     * مفید برای query تقویم
     */
    public function jalaliMonthBoundaries(int $year, int $month): array
    {
        // اول ماه
        [$gy1, $gm1, $gd1] = CalendarUtils::toGregorian($year, $month, 1);
        $start = CarbonImmutable::create($gy1, $gm1, $gd1, 0, 0, 0);

        // آخر ماه (روز ۱ ماه بعد منهای ۱ ثانیه)
        if ($month === 12) {
            $nextYear = $year + 1;
            $nextMonth = 1;
        } else {
            $nextYear = $year;
            $nextMonth = $month + 1;
        }

        [$gy2, $gm2, $gd2] = CalendarUtils::toGregorian($nextYear, $nextMonth, 1);
        $end = CarbonImmutable::create($gy2, $gm2, $gd2, 0, 0, 0)->subSecond();

        return ['start' => $start, 'end' => $end];
    }

    /**
     * نام ماه شمسی
     */
    public function getMonthName(int $month): string
    {
        return [
            1 => 'فروردین',
            2 => 'اردیبهشت',
            3 => 'خرداد',
            4 => 'تیر',
            5 => 'مرداد',
            6 => 'شهریور',
            7 => 'مهر',
            8 => 'آبان',
            9 => 'آذر',
            10 => 'دی',
            11 => 'بهمن',
            12 => 'اسفند',
        ][$month] ?? '';
    }

    /**
     * نام روز هفته فارسی (شنبه = 6 در ISO ولی در ایران = ابتدای هفته)
     */
    public function getDayName(\DateTimeInterface $date): string
    {
        $jalali = Jalalian::fromDateTime($date);
        return [
            'Saturday' => 'شنبه',
            'Sunday' => 'یکشنبه',
            'Monday' => 'دوشنبه',
            'Tuesday' => 'سه‌شنبه',
            'Wednesday' => 'چهارشنبه',
            'Thursday' => 'پنج‌شنبه',
            'Friday' => 'جمعه',
        ][$date->format('l')] ?? '';
    }

    /**
     * آیا روز کاری است؟ (شنبه تا چهارشنبه پیش‌فرض)
     */
    public function isWorkingDay(\DateTimeInterface $date, array $workingDays = null): bool
    {
        $workingDays = $workingDays ?? config('mms.calendar.working_days', [6, 0, 1, 2, 3]);
        // 0=یکشنبه, 6=شنبه (PHP day-of-week)
        $dow = (int) $date->format('w');
        return in_array($dow, $workingDays, true);
    }

    /**
     * بازه ۷ روز هفته که این تاریخ در آن قرار دارد (شروع شنبه)
     */
    public function weekBoundaries(\DateTimeInterface $date): array
    {
        $carbon = Carbon::instance(new \DateTime($date->format('c')));

        // شنبه ابتدای هفته در ایران
        $start = $carbon->copy()->startOfDay();
        while ($start->format('l') !== 'Saturday') {
            $start->subDay();
        }
        $end = $start->copy()->addDays(6)->endOfDay();

        return ['start' => $start->toImmutable(), 'end' => $end->toImmutable()];
    }
}
