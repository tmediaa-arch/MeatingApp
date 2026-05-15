<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Actions;

use App\Domains\Integrations\DTOs\HealthCheckResult;
use App\Domains\Integrations\Models\IntegrationProvider;
use App\Domains\Integrations\Services\IntegrationProviderManager;

/**
 * TestProviderConnectionAction — تست اتصال/سلامت یک integration provider.
 */
class TestProviderConnectionAction
{
    public function __construct(
        private readonly IntegrationProviderManager $manager,
    ) {
    }

    public function execute(IntegrationProvider $provider): HealthCheckResult
    {
        $start = microtime(true);

        try {
            $driver = $this->manager->resolve($provider);
            $result = $driver->checkHealth();
            $elapsed = (int) round((microtime(true) - $start) * 1000);

            return new HealthCheckResult(
                status: $result->status,
                message: $result->message,
                latencyMs: $result->latencyMs ?? $elapsed,
            );
        } catch (\Throwable $e) {
            return HealthCheckResult::down('اتصال ناموفق: ' . $e->getMessage());
        }
    }
}
