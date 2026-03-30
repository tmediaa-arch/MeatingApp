<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Adapters;

use App\Domains\Integrations\Contracts\IntegrationDriverInterface;
use App\Domains\Integrations\DTOs\HealthCheckResult;
use App\Domains\Integrations\Models\IntegrationProvider;

abstract class AbstractIntegrationAdapter implements IntegrationDriverInterface
{
    public function __construct(
        protected readonly IntegrationProvider $provider,
    ) {
    }

    abstract public function checkHealth(): HealthCheckResult;

    abstract public function getName(): string;

    protected function config(string $key, mixed $default = null): mixed
    {
        return $this->provider->getConfigValue($key, $default);
    }
}
