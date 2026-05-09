<?php

declare(strict_types=1);

namespace App\Domains\Resolutions\Exceptions;

use App\Domains\Resolutions\Enums\ResolutionStatus;
use DomainException;

class ResolutionException extends DomainException
{
    public static function minuteNotSigned(): self
    {
        return new self('مصوبه فقط روی صورتجلسه امضا شده قابل ایجاد است.');
    }

    public static function invalidStateTransition(ResolutionStatus $from, ResolutionStatus $to): self
    {
        return new self(sprintf(
            'انتقال از "%s" به "%s" در مصوبه مجاز نیست.',
            $from->label(),
            $to->label(),
        ));
    }

    public static function cannotEditTerminal(ResolutionStatus $status): self
    {
        return new self(sprintf('مصوبه با وضعیت "%s" قابل ویرایش نیست.', $status->label()));
    }

    public static function votingNotOpen(): self
    {
        return new self('رأی‌گیری در حال حاضر باز نیست.');
    }

    public static function alreadyVoted(): self
    {
        return new self('شما قبلاً به این مصوبه رأی داده‌اید.');
    }

    public static function votingDoesNotRequire(): self
    {
        return new self('این مصوبه نیاز به رأی‌گیری ندارد.');
    }

    public static function quorumNotReached(int $required, int $actual): self
    {
        return new self(sprintf(
            'حد نصاب رسمیت رأی‌گیری حاصل نشد (موردنیاز: %d، حاضر: %d).',
            $required,
            $actual,
        ));
    }
}
