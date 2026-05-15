<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Jobs;

use App\Domains\Identity\Models\User;
use App\Domains\Integrations\Models\IntegrationProvider;
use App\Domains\Integrations\Services\IntegrationSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * RunIntegrationSyncJob — اجرای async یک عملیات sync برای یک provider.
 */
class RunIntegrationSyncJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $providerId,
        public readonly string $syncType = 'manual',
        public readonly ?int $userId = null,
    ) {
    }

    public function handle(IntegrationSyncService $syncService): void
    {
        $provider = IntegrationProvider::find($this->providerId);
        if ($provider === null) {
            return;
        }

        $triggeredBy = $this->userId !== null ? User::find($this->userId) : null;

        $syncService->sync($provider, $this->syncType, $triggeredBy);
    }
}
