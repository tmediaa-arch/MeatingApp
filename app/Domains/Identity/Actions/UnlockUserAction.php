<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Events\UserStatusChanged;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\DB;

class UnlockUserAction
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {
    }

    public function execute(User $user, ?string $reason = null): User
    {
        if (!$user->isLocked()) {
            return $user; // idempotent
        }

        return DB::transaction(function () use ($user, $reason) {
            $previousStatus = $user->status;

            $user->update([
                'status' => UserStatus::Active,
                'locked_until' => null,
                'failed_login_attempts' => 0,
            ]);

            $this->auditService->log(
                event: 'user_unlocked',
                auditable: $user,
                description: "کاربر '{$user->username}' باز شد" . ($reason ? ". دلیل: {$reason}" : ''),
                oldValues: ['status' => $previousStatus->value],
                newValues: ['status' => UserStatus::Active->value],
                context: ['reason' => $reason],
                severity: 'notice',
            );

            event(new UserStatusChanged(
                user: $user,
                previousStatus: $previousStatus,
                newStatus: UserStatus::Active,
                reason: $reason,
            ));

            return $user->fresh();
        });
    }
}
