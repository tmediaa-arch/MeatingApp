<?php

declare(strict_types=1);

namespace App\Domains\VideoConference\Exceptions;

use DomainException;

class VideoConferenceException extends DomainException
{
    public static function providerNotFound(): self
    {
        return new self('هیچ provider فعال برای ایجاد اتاق یافت نشد.');
    }

    public static function driverNotSupported(string $driver): self
    {
        return new self("Driver '{$driver}' پشتیبانی نمی‌شود.");
    }

    public static function providerUnhealthy(string $name): self
    {
        return new self("Provider '{$name}' در وضعیت سالم نیست.");
    }

    public static function maxConcurrentReached(string $name, int $max): self
    {
        return new self("Provider '{$name}' به حداکثر همزمانی ({$max}) رسیده است.");
    }

    public static function externalApiFailed(string $message): self
    {
        return new self("خطا در ارتباط با provider: {$message}");
    }

    public static function invalidTransition(string $from, string $to): self
    {
        return new self("تغییر وضعیت اتاق از '{$from}' به '{$to}' مجاز نیست.");
    }

    public static function roomNotActive(): self
    {
        return new self('اتاق در حال برگزاری نیست.');
    }

    public static function recordingNotSupported(string $driver): self
    {
        return new self("Driver '{$driver}' از ضبط پشتیبانی نمی‌کند.");
    }

    public static function meetingAlreadyHasRoom(): self
    {
        return new self('این جلسه قبلاً اتاق ویدئوکنفرانس دارد.');
    }
}
