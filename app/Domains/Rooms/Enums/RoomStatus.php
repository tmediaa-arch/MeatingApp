<?php

declare(strict_types=1);

namespace App\Domains\Rooms\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum RoomStatus: string implements HasColor, HasIcon, HasLabel
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Maintenance = 'maintenance';
    case Reserved = 'reserved';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'فعال',
            self::Inactive => 'غیرفعال',
            self::Maintenance => 'در حال تعمیر',
            self::Reserved => 'رزرو شده',
            self::Closed => 'بسته',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Inactive => 'gray',
            self::Maintenance => 'warning',
            self::Reserved => 'info',
            self::Closed => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Active => 'heroicon-o-check-circle',
            self::Inactive => 'heroicon-o-pause-circle',
            self::Maintenance => 'heroicon-o-wrench-screwdriver',
            self::Reserved => 'heroicon-o-calendar',
            self::Closed => 'heroicon-o-x-circle',
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
