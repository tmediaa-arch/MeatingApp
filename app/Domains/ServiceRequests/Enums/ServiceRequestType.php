<?php

declare(strict_types=1);

namespace App\Domains\ServiceRequests\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ServiceRequestType: string implements HasColor, HasIcon, HasLabel
{
    case Transport = 'transport';
    case Catering = 'catering';
    case Equipment = 'equipment';
    case Support = 'support';
    case VenueSetup = 'venue_setup';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Transport => 'نقلیه',
            self::Catering => 'پذیرایی',
            self::Equipment => 'تجهیزات',
            self::Support => 'پشتیبانی فنی',
            self::VenueSetup => 'چیدمان سالن',
            self::Other => 'سایر',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Transport => 'heroicon-o-truck',
            self::Catering => 'heroicon-o-cake',
            self::Equipment => 'heroicon-o-computer-desktop',
            self::Support => 'heroicon-o-wrench-screwdriver',
            self::VenueSetup => 'heroicon-o-building-office',
            self::Other => 'heroicon-o-ellipsis-horizontal-circle',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Transport => 'amber',
            self::Catering => 'pink',
            self::Equipment => 'blue',
            self::Support => 'gray',
            self::VenueSetup => 'purple',
            self::Other => 'gray',
        };
    }

    public function typeSpecificFields(): array
    {
        return match ($this) {
            self::Transport => [
                'origin' => 'مبدأ',
                'destination' => 'مقصد',
                'passenger_count' => 'تعداد سرنشین',
                'vehicle_type' => 'نوع خودرو',
                'return_trip' => 'برگشت',
            ],
            self::Catering => [
                'guest_count' => 'تعداد نفرات',
                'menu' => 'منو',
                'dietary_restrictions' => 'محدودیت‌های غذایی',
                'service_type' => 'نوع سرویس (پذیرایی/بوفه)',
            ],
            self::Equipment => [
                'equipment_list' => 'فهرست تجهیزات',
                'setup_required' => 'نیاز به نصب',
                'technician_required' => 'نیاز به تکنیسین',
            ],
            self::Support => [
                'issue_category' => 'دسته مشکل',
                'urgency_level' => 'سطح فوریت',
                'affected_systems' => 'سیستم‌های متأثر',
            ],
            self::VenueSetup => [
                'layout_type' => 'نوع چیدمان',
                'seat_count' => 'تعداد صندلی',
                'av_equipment' => 'تجهیزات سمعی-بصری',
            ],
            self::Other => [],
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($t) => [$t->value => $t->label()])
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

    public function getIcon(): string|\BackedEnum|\Illuminate\Contracts\Support\Htmlable|null
    {
        return $this->icon();
    }
}
