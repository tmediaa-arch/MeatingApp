<?php

declare(strict_types=1);

namespace App\Domains\Identity\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum UserStatus: string implements HasColor, HasIcon, HasLabel
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Locked = 'locked';
    case Suspended = 'suspended';
    case Pending = 'pending';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'فعال',
            self::Inactive => 'غیرفعال',
            self::Locked => 'قفل‌شده',
            self::Suspended => 'معلق',
            self::Pending => 'در انتظار تأیید',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Inactive => 'gray',
            self::Locked => 'danger',
            self::Suspended => 'warning',
            self::Pending => 'info',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Active => 'heroicon-o-check-circle',
            self::Inactive => 'heroicon-o-pause-circle',
            self::Locked => 'heroicon-o-lock-closed',
            self::Suspended => 'heroicon-o-exclamation-triangle',
            self::Pending => 'heroicon-o-clock',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string|array|null
    {
        return $this->color();
    }

    public function getIcon(): string|\BackedEnum|\Illuminate\Contracts\Support\Htmlable|null
    {
        return $this->icon();
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($c) => [$c->value => $c->label()])
            ->toArray();
    }

    /**
     * آیا کاربر با این وضعیت اجازه ورود دارد؟
     * فقط وضعیت Active اجازه ورود می‌دهد.
     */
    public function canLogin(): bool
    {
        return $this === self::Active;
    }
}
