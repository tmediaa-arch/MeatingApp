<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Enums;

enum IntegrationHealthStatus: string
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
}
