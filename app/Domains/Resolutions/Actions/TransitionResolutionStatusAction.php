<?php

declare(strict_types=1);

namespace App\Domains\Resolutions\Actions;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Resolutions\Enums\ResolutionStatus;
use App\Domains\Resolutions\Exceptions\ResolutionException;
use App\Domains\Resolutions\Models\Resolution;
use Illuminate\Support\Facades\DB;

class TransitionResolutionStatusAction
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {
    }

    public function execute(
        Resolution $resolution,
        ResolutionStatus $newStatus,
        ?string $reason = null,
    ): Resolution {
        if ($resolution->status === $newStatus) {
            return $resolution;
        }

        if (!$resolution->status->canTransitionTo($newStatus)) {
            throw ResolutionException::invalidStateTransition($resolution->status, $newStatus);
        }

        return DB::transaction(function () use ($resolution, $newStatus, $reason) {
            $oldStatus = $resolution->status;

            $updates = ['status' => $newStatus];

            // تنظیم timestamps مرتبط
            if ($newStatus === ResolutionStatus::Completed) {
                $updates['completed_at'] = now();
            } elseif ($newStatus === ResolutionStatus::Cancelled) {
                $updates['cancelled_at'] = now();
                $updates['cancellation_reason'] = $reason;
            }

            $resolution->update($updates);

            $this->auditService->log(
                event: 'resolution_status_changed',
                auditable: $resolution,
                description: sprintf(
                    'وضعیت مصوبه "%s" از "%s" به "%s" تغییر کرد',
                    $resolution->resolution_number,
                    $oldStatus->label(),
                    $newStatus->label(),
                ),
                oldValues: ['status' => $oldStatus->value],
                newValues: ['status' => $newStatus->value],
                context: ['reason' => $reason],
                severity: 'info',
            );

            return $resolution->fresh();
        });
    }
}
