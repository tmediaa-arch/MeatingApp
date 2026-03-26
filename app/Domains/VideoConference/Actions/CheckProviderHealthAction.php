<?php

declare(strict_types=1);

namespace App\Domains\VideoConference\Actions;

use App\Domains\VideoConference\Models\VideoConferenceProvider;
use App\Domains\VideoConference\Services\VideoConferenceProviderManager;

/**
 * بررسی سلامت یک Provider و به‌روزرسانی health_status در DB.
 */
class CheckProviderHealthAction
{
    public function __construct(
        private readonly VideoConferenceProviderManager $providerManager,
    ) {
    }

    public function execute(VideoConferenceProvider $provider): VideoConferenceProvider
    {
        try {
            $adapter = $this->providerManager->resolve($provider);
            $result = $adapter->checkHealth();

            $provider->update([
                'health_status' => $result->status,
                'health_message' => $result->message,
                'last_health_check_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $provider->update([
                'health_status' => \App\Domains\VideoConference\Enums\HealthStatus::Unhealthy,
                'health_message' => $e->getMessage(),
                'last_health_check_at' => now(),
            ]);
        }

        return $provider->fresh();
    }
}
