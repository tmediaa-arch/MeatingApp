<?php

declare(strict_types=1);

namespace App\Domains\Meetings\Exceptions;

use App\Domains\Meetings\Enums\MeetingStatus;

class MeetingException extends \DomainException
{
    public static function invalidStateTransition(MeetingStatus $from, MeetingStatus $to): self
    {
        return new self(sprintf(
            'انتقال جلسه از وضعیت "%s" به "%s" مجاز نیست.',
            $from->label(),
            $to->label(),
        ));
    }

    public static function cannotCancelTerminal(MeetingStatus $current): self
    {
        return new self(sprintf(
            'جلسه با وضعیت "%s" قابل لغو نیست.',
            $current->label(),
        ));
    }

    public static function cannotEditInStatus(MeetingStatus $current): self
    {
        return new self(sprintf(
            'جلسه با وضعیت "%s" قابل ویرایش نیست.',
            $current->label(),
        ));
    }

    public static function invalidScheduleRange(): self
    {
        return new self('زمان پایان جلسه باید بعد از زمان شروع باشد.');
    }

    public static function scheduleInPast(): self
    {
        return new self('برنامه‌ریزی جلسه در گذشته مجاز نیست.');
    }

    public static function roomNotAvailable(string $roomName): self
    {
        return new self(sprintf('سالن "%s" در این بازه زمانی در دسترس نیست.', $roomName));
    }

    public static function roomCapacityExceeded(int $capacity, int $expected): self
    {
        return new self(sprintf(
            'ظرفیت سالن (%d نفر) برای تعداد شرکت‌کنندگان (%d) کافی نیست.',
            $capacity, $expected,
        ));
    }

    public static function noChairpersonForKeyMeeting(): self
    {
        return new self('برای این نوع جلسه، تعیین رئیس جلسه الزامی است.');
    }

    public static function externalParticipantsNotAllowed(): self
    {
        return new self('در این جلسه افراد خارج از سازمان مجاز نیستند.');
    }
}
