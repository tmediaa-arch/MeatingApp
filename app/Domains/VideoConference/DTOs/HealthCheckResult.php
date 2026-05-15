<?php

declare(strict_types=1);

namespace App\Domains\VideoConference\DTOs;

/**
 * HealthCheckResult — نتیجه بررسی سلامت یک provider ویدئوکنفرانس.
 */
final class HealthCheckResult
{
    public function __construct(
        public readonly string $status,
        public readonly ?string $message = null,
        public readonly int|float $latencyMs = 0,
    ) {
    }

    public static function healthy(int|float $latencyMs = 0): self
    {
        return new self('healthy', null, $latencyMs);
    }

    public static function degraded(string $message): self
    {
        return new self('degraded', $message);
    }

    public static function unhealthy(string $message): self
    {
        return new self('unhealthy', $message);
    }

    public function isHealthy(): bool
    {
        return $this->status === 'healthy';
    }
}
