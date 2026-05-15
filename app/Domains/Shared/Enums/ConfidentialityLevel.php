<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ConfidentialityLevel: string implements HasColor, HasIcon, HasLabel
{
    case Public = 'public';
    case Internal = 'internal';
    case Confidential = 'confidential';
    case Restricted = 'restricted';
    case Secret = 'secret';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'عمومی',
            self::Internal => 'داخلی',
            self::Confidential => 'محرمانه',
            self::Restricted => 'محدود',
            self::Secret => 'سری',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Public => 'gray',
            self::Internal => 'info',
            self::Confidential => 'warning',
            self::Restricted => 'danger',
            self::Secret => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Public => 'heroicon-o-globe-alt',
            self::Internal => 'heroicon-o-building-office',
            self::Confidential => 'heroicon-o-eye-slash',
            self::Restricted => 'heroicon-o-lock-closed',
            self::Secret => 'heroicon-o-shield-exclamation',
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

    public function isHigherThan(self $other): bool
    {
        $order = [
            self::Public->value => 0,
            self::Internal->value => 1,
            self::Confidential->value => 2,
            self::Restricted->value => 3,
            self::Secret->value => 4,
        ];

        return $order[$this->value] > $order[$other->value];
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($c) => [$c->value => $c->label()])
            ->toArray();
    }
}
