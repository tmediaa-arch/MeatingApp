<?php

declare(strict_types=1);

namespace App\Domains\Meetings\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum InvitationStatus: string implements HasColor, HasLabel
{
    case NotInvited = 'not_invited';
    case Invited = 'invited';
    case Accepted = 'accepted';
    case Tentative = 'tentative';
    case Declined = 'declined';
    case NoResponse = 'no_response';

    public function label(): string
    {
        return match ($this) {
            self::NotInvited => 'هنوز دعوت نشده',
            self::Invited => 'دعوت‌نامه ارسال شد',
            self::Accepted => 'تأیید کرد',
            self::Tentative => 'شاید (مشروط)',
            self::Declined => 'رد کرد',
            self::NoResponse => 'بدون پاسخ',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::NotInvited => 'gray',
            self::Invited => 'cyan',
            self::Accepted => 'success',
            self::Tentative => 'warning',
            self::Declined => 'danger',
            self::NoResponse => 'gray',
        };
    }

    public function isResponded(): bool
    {
        return in_array($this, [self::Accepted, self::Tentative, self::Declined], true);
    }

    public function isPositive(): bool
    {
        return in_array($this, [self::Accepted, self::Tentative], true);
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($c) => [$c->value => $c->label()])
            ->toArray();
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string|array|null
    {
        return $this->color();
    }
}
