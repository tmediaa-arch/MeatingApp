<?php

declare(strict_types=1);

namespace App\Domains\Rooms\Actions;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Rooms\Enums\ReservationStatus;
use App\Domains\Rooms\Models\RoomReservation;

class CancelReservationAction
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {
    }

    public function execute(RoomReservation $reservation, string $reason): RoomReservation
    {
        if (!in_array($reservation->status, [
            ReservationStatus::Pending,
            ReservationStatus::Approved,
        ], true)) {
            throw new \DomainException('این رزرو قابل لغو نیست.');
        }

        $previous = $reservation->status->value;

        $reservation->update([
            'status' => ReservationStatus::Cancelled,
            'metadata' => array_merge($reservation->metadata ?? [], [
                'cancelled_at' => now()->toIso8601String(),
                'cancellation_reason' => $reason,
                'cancelled_by' => auth()->id(),
            ]),
        ]);

        $this->auditService->log(
            event: 'room_reservation_cancelled',
            auditable: $reservation,
            description: sprintf(
                "رزرو سالن '%s' لغو شد (دلیل: %s)",
                $reservation->room->name,
                $reason,
            ),
            oldValues: ['status' => $previous],
            newValues: ['status' => ReservationStatus::Cancelled->value],
            context: ['reason' => $reason],
            severity: 'notice',
        );

        return $reservation->fresh();
    }
}
