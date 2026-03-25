<?php

declare(strict_types=1);

namespace App\Domains\VideoConference\Enums;

enum HealthStatus: string
{
    case Unknown = 'unknown';
    case Healthy = 'healthy';
    case Degraded = 'degraded';
    case Unhealthy = 'unhealthy';

    public function label(): string
    {
        return match ($this) {
            self::Unknown => 'نامشخص',
            self::Healthy => 'سالم',
            self::Degraded => 'تنزل یافته',
            self::Unhealthy => 'ناسالم',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Unknown => 'gray',
            self::Healthy => 'success',
            self::Degraded => 'warning',
            self::Unhealthy => 'danger',
        };
    }

    public function isUsable(): bool
    {
        return in_array($this, [self::Healthy, self::Degraded, self::Unknown], true);
    }
}
