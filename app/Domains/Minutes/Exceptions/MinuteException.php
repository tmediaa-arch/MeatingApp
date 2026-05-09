<?php

declare(strict_types=1);

namespace App\Domains\Minutes\Exceptions;

use App\Domains\Minutes\Enums\MinuteStatus;
use DomainException;

class MinuteException extends DomainException
{
    public static function meetingNotCompleted(): self
    {
        return new self('صورتجلسه فقط برای جلسات برگزار شده قابل ایجاد است.');
    }

    public static function alreadyExists(): self
    {
        return new self('برای این جلسه از قبل صورتجلسه ایجاد شده است.');
    }

    public static function invalidStateTransition(MinuteStatus $from, MinuteStatus $to): self
    {
        return new self(sprintf(
            'انتقال از "%s" به "%s" در state machine صورتجلسه مجاز نیست.',
            $from->label(),
            $to->label(),
        ));
    }

    public static function cannotEditInStatus(MinuteStatus $status): self
    {
        return new self(sprintf(
            'صورتجلسه در وضعیت "%s" قابل ویرایش نیست.',
            $status->label(),
        ));
    }

    public static function cannotSignInStatus(MinuteStatus $status): self
    {
        return new self(sprintf(
            'صورتجلسه در وضعیت "%s" قابل امضا نیست.',
            $status->label(),
        ));
    }

    public static function alreadySigned(string $role): self
    {
        return new self(sprintf(
            'این صورتجلسه قبلاً توسط "%s" امضا شده است.',
            $role,
        ));
    }

    public static function notAuthorizedToSignAsRole(string $role): self
    {
        return new self(sprintf(
            'شما مجاز به امضای صورتجلسه به‌عنوان "%s" نیستید.',
            $role,
        ));
    }

    public static function notFullySigned(): self
    {
        return new self('صورتجلسه قابل انتشار نیست؛ ابتدا باید توسط دبیر و رئیس امضا شود.');
    }
}
