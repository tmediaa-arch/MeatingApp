<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum IntegrationHealthStatus: string implements HasColor, HasLabel
{
    case Healthy = 'healthy';
    case Degraded = 'degraded';
    case Down = 'down';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Healthy => 'سالم',
            self::Degraded => 'با مشکل',
            self::Down => 'قطع',
            self::Unknown => 'نامشخص',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Healthy => 'success',
            self::Degraded => 'warning',
            self::Down => 'danger',
            self::Unknown => 'gray',
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
}
