<?php

declare(strict_types=1);

namespace App\Domains\Organization\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum OrgUnitType: string implements HasColor, HasIcon, HasLabel
{
    case Organization = 'organization';
    case Division = 'division';
    case Department = 'department';
    case Office = 'office';
    case Team = 'team';
    case Committee = 'committee';

    public function label(): string
    {
        return match ($this) {
            self::Organization => 'سازمان',
            self::Division => 'معاونت',
            self::Department => 'اداره',
            self::Office => 'دفتر',
            self::Team => 'تیم',
            self::Committee => 'کمیته',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Organization => 'primary',
            self::Division => 'info',
            self::Department => 'success',
            self::Office => 'warning',
            self::Team => 'gray',
            self::Committee => 'purple',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Organization => 'heroicon-o-building-office-2',
            self::Division => 'heroicon-o-building-office',
            self::Department => 'heroicon-o-rectangle-stack',
            self::Office => 'heroicon-o-briefcase',
            self::Team => 'heroicon-o-user-group',
            self::Committee => 'heroicon-o-users',
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
}
