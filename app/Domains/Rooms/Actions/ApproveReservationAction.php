<?php

declare(strict_types=1);

namespace App\Domains\Rooms\Actions;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Identity\Models\User;
use App\Domains\Rooms\Enums\ReservationStatus;
use App\Domains\Rooms\Models\RoomReservation;

class ApproveReservationAction
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {
    }

    public function execute(RoomReservation $reservation, User $approver): RoomReservation
    {
        if ($reservation->status !== ReservationStatus::Pending) {
            throw new \DomainException(
                sprintf('رزرو با وضعیت "%s" قابل تأیید نیست.', $reservation->status->label())
            );
        }

        if (!$reservation->canBeApprovedBy($approver)) {
            throw new \DomainException('شما اجازه تأیید این رزرو را ندارید.');
        }

        $reservation->update([
            'status' => ReservationStatus::Approved,
            'approved_by_user_id' => $approver->id,
            'approved_at' => now(),
        ]);

        $this->auditService->log(
            event: 'room_reservation_approved',
            auditable: $reservation,
            description: sprintf(
                "رزرو سالن '%s' توسط '%s' تأیید شد",
                $reservation->room->name,
                $approver->name,
            ),
            oldValues: ['status' => 'pending'],
            newValues: ['status' => 'approved'],
            severity: 'notice',
        );

        return $reservation->fresh();
    }
}
