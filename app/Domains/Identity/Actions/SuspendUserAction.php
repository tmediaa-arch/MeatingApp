<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Events\UserStatusChanged;
use App\Domains\Identity\Exceptions\IdentityException;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\DB;

class SuspendUserAction
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {
    }

    /**
     * @throws IdentityException
     */
    public function execute(User $user, string $reason, ?\DateTimeInterface $until = null): User
    {
        if ($user->is_system) {
            throw new IdentityException('System users cannot be suspended.');
        }

        if ($user->status === UserStatus::Suspended) {
            // idempotent — no-op
            return $user;
        }

        if (!$user->status->canTransitionTo(UserStatus::Suspended)) {
            throw IdentityException::invalidStateTransition(
                $user->status->value,
                UserStatus::Suspended->value
            );
        }

        return DB::transaction(function () use ($user, $reason, $until) {
            $previousStatus = $user->status;

            $user->update([
                'status' => UserStatus::Suspended,
                'locked_until' => $until,
            ]);

            // Invalidate active sessions
            // (در فاز بعدی sessions این کاربر هم force logout می‌شوند)
            DB::table('sessions')->where('user_id', $user->id)->delete();

            $this->auditService->log(
                event: 'user_suspended',
                auditable: $user,
                description: "کاربر '{$user->username}' تعلیق شد. دلیل: {$reason}",
                oldValues: ['status' => $previousStatus->value],
                newValues: ['status' => UserStatus::Suspended->value],
                context: ['reason' => $reason, 'until' => $until?->format('Y-m-d H:i:s')],
                severity: 'warning',
            );

            event(new UserStatusChanged(
                user: $user,
                previousStatus: $previousStatus,
                newStatus: UserStatus::Suspended,
                reason: $reason,
            ));

            return $user->fresh();
        });
    }
}
