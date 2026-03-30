<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Services;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Identity\Models\User;
use App\Domains\Integrations\Contracts\HrsDriverInterface;
use App\Domains\Integrations\Contracts\LdapDriverInterface;
use App\Domains\Integrations\DTOs\SyncResult;
use App\Domains\Integrations\Enums\SyncDirection;
use App\Domains\Integrations\Enums\SyncStatus;
use App\Domains\Integrations\Models\IntegrationProvider;
use App\Domains\Integrations\Models\IntegrationSyncLog;

/**
 * IntegrationSyncService — اجرا و log کردن sync ها.
 *
 * این سرویس wrapper مشترکی برای driverها است و:
 * - یک IntegrationSyncLog با status=running می‌سازد
 * - driver را اجرا می‌کند
 * - نتیجه را در log می‌نویسد (با محدودیت append-only)
 * - statistics provider را به‌روز می‌کند
 */
class IntegrationSyncService
{
    public function __construct(
        private readonly IntegrationProviderManager $manager,
        private readonly AuditService $auditService,
    ) {
    }

    public function sync(
        IntegrationProvider $provider,
        string $syncType = 'manual',
        ?User $triggeredBy = null,
    ): IntegrationSyncLog {
        if (!$provider->type->supportsSync()) {
            throw new \DomainException("Provider با type {$provider->type->value} از sync پشتیبانی نمی‌کند.");
        }

        $log = IntegrationSyncLog::create([
            'provider_id' => $provider->id,
            'triggered_by_user_id' => $triggeredBy?->id,
            'sync_type' => $syncType,
            'direction' => SyncDirection::Inbound,
            'status' => SyncStatus::Running,
            'started_at' => now(),
        ]);

        $start = microtime(true);

        try {
            $driver = $this->manager->resolve($provider);

            $result = match (true) {
                $driver instanceof LdapDriverInterface => $driver->syncAllUsers(),
                $driver instanceof HrsDriverInterface => $driver->syncEmployees($provider->last_sync_at),
                default => throw new \LogicException('driver از sync پشتیبانی نمی‌کند: ' . get_class($driver)),
            };

            $duration = (int) ((microtime(true) - $start) * 1000);
            $status = $result->isComplete()
                ? SyncStatus::Completed
                : ($result->isPartial() ? SyncStatus::Partial : SyncStatus::Failed);

            $log->update([
                'status' => $status,
                'completed_at' => now(),
                'duration_ms' => $duration,
                'records_processed' => $result->processed,
                'records_created' => $result->created,
                'records_updated' => $result->updated,
                'records_skipped' => $result->skipped,
                'records_failed' => $result->failed,
                'error_summary' => $result->errors,
                'metadata' => $result->metadata,
            ]);

            // به‌روز رسانی statistics provider
            $provider->forceFill([
                'total_syncs' => $provider->total_syncs + 1,
                'successful_syncs' => $provider->successful_syncs + ($status === SyncStatus::Completed ? 1 : 0),
                'last_sync_at' => now(),
            ])->save();

            $this->auditService->log(
                event: 'integration_sync_completed',
                auditable: $log,
                description: sprintf(
                    'Sync %s — %d رکورد پردازش (%d ایجاد، %d بروز، %d خطا)',
                    $provider->display_name,
                    $result->processed,
                    $result->created,
                    $result->updated,
                    $result->failed,
                ),
                severity: $status === SyncStatus::Failed ? 'error' : 'info',
            );
        } catch (\Throwable $e) {
            $duration = (int) ((microtime(true) - $start) * 1000);

            $log->update([
                'status' => SyncStatus::Failed,
                'completed_at' => now(),
                'duration_ms' => $duration,
                'error_summary' => [['id' => '__exception__', 'reason' => $e->getMessage()]],
                'full_log' => substr($e->getTraceAsString(), 0, 5000),
            ]);

            $provider->forceFill([
                'total_syncs' => $provider->total_syncs + 1,
                'last_sync_at' => now(),
            ])->save();

            $this->auditService->log(
                event: 'integration_sync_failed',
                auditable: $log,
                description: "Sync {$provider->display_name} با خطا مواجه شد: " . $e->getMessage(),
                severity: 'error',
            );

            throw $e;
        }

        return $log->fresh();
    }
}
