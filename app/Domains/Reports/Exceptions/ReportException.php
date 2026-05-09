<?php

declare(strict_types=1);

namespace App\Domains\Reports\Exceptions;

class ReportException extends \DomainException
{
    public static function notFound(string $key): self
    {
        return new self("گزارش با کلید «{$key}» یافت نشد.");
    }

    public static function reportInactive(string $key): self
    {
        return new self("گزارش «{$key}» در حال حاضر غیرفعال است.");
    }

    public static function unsupportedFormat(string $key, string $format): self
    {
        return new self("گزارش «{$key}» از فرمت «{$format}» پشتیبانی نمی‌کند.");
    }

    public static function executionFailed(string $key, string $reason): self
    {
        return new self("اجرای گزارش «{$key}» با خطا مواجه شد: {$reason}");
    }

    public static function invalidInput(string $reason): self
    {
        return new self("ورودی گزارش نامعتبر است: {$reason}");
    }

    public static function notAuthorized(): self
    {
        return new self('شما مجوز اجرای این گزارش را ندارید.');
    }
}
