<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Exceptions;

class IntegrationException extends \DomainException
{
    public static function providerNotFound(string $key): self
    {
        return new self("Provider با کلید «{$key}» یافت نشد.");
    }

    public static function driverNotSupported(string $type, string $driver): self
    {
        return new self("driver «{$driver}» برای type «{$type}» پشتیبانی نمی‌شود.");
    }

    public static function authenticationFailed(string $reason = ''): self
    {
        return new self("احراز هویت ناموفق بود" . ($reason ? ": {$reason}" : '.'));
    }

    public static function syncInProgress(): self
    {
        return new self('یک sync دیگر در حال اجراست. لطفاً منتظر بمانید.');
    }

    public static function invalidConfig(string $reason): self
    {
        return new self("پیکربندی نامعتبر: {$reason}");
    }
}
