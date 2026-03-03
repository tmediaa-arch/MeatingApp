<?php

declare(strict_types=1);

namespace App\Domains\Minutes\Actions;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Minutes\Enums\MinuteStatus;
use App\Domains\Minutes\Exceptions\MinuteException;
use App\Domains\Minutes\Models\Minute;
use Illuminate\Support\Facades\DB;

class TransitionMinuteStatusAction
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {
    }

    public function execute(
        Minute $minute,
        MinuteStatus $newStatus,
        ?string $reason = null,
    ): Minute {
        if ($minute->status === $newStatus) {
            return $minute; // idempotent
        }

        if (!$minute->status->canTransitionTo($newStatus)) {
            throw MinuteException::invalidStateTransition($minute->status, $newStatus);
        }

        return DB::transaction(function () use ($minute, $newStatus, $reason) {
            $oldStatus = $minute->status;

            $minute->update(['status' => $newStatus]);

            $this->auditService->log(
                event: 'minute_status_changed',
                auditable: $minute,
                description: sprintf(
                    'وضعیت صورتجلسه "%s" از "%s" به "%s" تغییر کرد',
                    $minute->minute_number,
                    $oldStatus->label(),
                    $newStatus->label(),
                ),
                oldValues: ['status' => $oldStatus->value],
                newValues: ['status' => $newStatus->value],
                context: ['reason' => $reason],
                severity: 'info',
            );

            return $minute->fresh();
        });
    }
}
