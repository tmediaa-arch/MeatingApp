<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Contracts;

use App\Domains\Integrations\DTOs\HealthCheckResult;

interface IntegrationDriverInterface
{
    /**
     * Health check provider — اتصال، احراز هویت، در دسترس بودن
     */
    public function checkHealth(): HealthCheckResult;

    /**
     * نام driver
     */
    public function getName(): string;
}
