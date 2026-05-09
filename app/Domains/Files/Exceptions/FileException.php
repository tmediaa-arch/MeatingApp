<?php

declare(strict_types=1);

namespace App\Domains\Files\Exceptions;

/**
 * استثناءهای دامنه فایل.
 *
 * متدهای factory پیام‌های فارسی استاندارد را برای کاربر تولید می‌کنند.
 */
class FileException extends \DomainException
{
    public static function uploadFailed(string $reason = ''): self
    {
        return new self("آپلود فایل با خطا مواجه شد." . ($reason ? " ({$reason})" : ''));
    }

    public static function fileNotFound(string $path = ''): self
    {
        return new self("فایل مورد نظر یافت نشد." . ($path ? " ({$path})" : ''));
    }

    public static function unsupportedMimeType(string $mime): self
    {
        return new self("نوع فایل ‹{$mime}› پشتیبانی نمی‌شود.");
    }

    public static function fileTooLarge(int $sizeBytes, int $limitBytes): self
    {
        $sizeMb = round($sizeBytes / 1024 / 1024, 2);
        $limitMb = round($limitBytes / 1024 / 1024, 2);

        return new self("حجم فایل ({$sizeMb}MB) از حد مجاز ({$limitMb}MB) بیشتر است.");
    }

    public static function hashMismatch(): self
    {
        return new self("یکپارچگی فایل تأیید نشد. ممکن است فایل دستکاری شده باشد.");
    }

    public static function notAccessible(): self
    {
        return new self("شما اجازه دسترسی به این فایل را ندارید.");
    }

    public static function virusDetected(): self
    {
        return new self("فایل آلوده به ویروس تشخیص داده شد و آپلود متوقف شد.");
    }

    public static function expired(): self
    {
        return new self("اعتبار این فایل به پایان رسیده است.");
    }

    public static function alreadyExists(string $hash): self
    {
        return new self("فایلی با همین محتوا قبلاً در سامانه ثبت شده است. (hash: {$hash})");
    }

    public static function permissionAlreadyExists(): self
    {
        return new self("این مجوز قبلاً صادر شده است.");
    }
}
