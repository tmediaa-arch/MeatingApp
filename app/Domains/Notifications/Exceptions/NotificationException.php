<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Exceptions;

use DomainException;

class NotificationException extends DomainException
{
    public static function templateNotFound(string $key): self
    {
        return new self("قالب اعلان '{$key}' یافت نشد.");
    }

    public static function channelNotSupported(string $key, string $channel): self
    {
        return new self("کانال '{$channel}' در قالب '{$key}' پشتیبانی نمی‌شود.");
    }

    public static function missingAddress(string $channel): self
    {
        return new self("آدرس گیرنده برای کانال '{$channel}' مشخص نیست.");
    }

    public static function maxAttemptsReached(): self
    {
        return new self('تعداد دفعات تلاش برای ارسال اعلان به حداکثر رسید.');
    }
}
