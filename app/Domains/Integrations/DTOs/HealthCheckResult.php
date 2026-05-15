<?php

declare(strict_types=1);

namespace App\Domains\Integrations\DTOs;

use App\Domains\Integrations\Enums\IntegrationHealthStatus;

/**
 * HealthCheckResult — نتیجه بررسی سلامت یک integration provider.
 */
final class HealthCheckResult
{
    public function __construct(
        public readonly IntegrationHealthStatus $status,
        public readonly string $message = '',
        public readonly ?int $latencyMs = null,
    ) {
    }

    public static function healthy(string $message = 'سالم', ?int $latencyMs = null): self
    {
        return new self(IntegrationHealthStatus::Healthy, $message, $latencyMs);
    }

    public static function degraded(string $message): self
    {
        return new self(IntegrationHealthStatus::Degraded, $message);
    }

    public static function down(string $message): self
    {
        return new self(IntegrationHealthStatus::Down, $message);
    }

    public function isHealthy(): bool
    {
        return $this->status === IntegrationHealthStatus::Healthy;
    }
}
