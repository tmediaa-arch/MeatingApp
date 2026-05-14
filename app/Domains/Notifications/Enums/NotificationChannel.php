<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum NotificationChannel: string implements HasIcon, HasLabel
{
    case Email = 'email';
    case Sms = 'sms';
    case InApp = 'in_app';
    case Push = 'push';
    case Webhook = 'webhook';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'ایمیل',
            self::Sms => 'پیامک',
            self::InApp => 'درون‌برنامه‌ای',
            self::Push => 'اعلان فوری',
            self::Webhook => 'وب‌هوک',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Email => 'heroicon-o-envelope',
            self::Sms => 'heroicon-o-device-phone-mobile',
            self::InApp => 'heroicon-o-bell',
            self::Push => 'heroicon-o-megaphone',
            self::Webhook => 'heroicon-o-globe-alt',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $s) => [$s->value => $s->label()])
            ->toArray();
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getIcon(): string|\BackedEnum|\Illuminate\Contracts\Support\Htmlable|null
    {
        return $this->icon();
    }
}
