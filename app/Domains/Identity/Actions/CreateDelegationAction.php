<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Identity\Exceptions\IdentityException;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\UserDelegation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CreateDelegationAction
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {
    }

    /**
     * @throws IdentityException
     */
    public function execute(
        User $delegator,
        User $delegate,
        Carbon $startsAt,
        Carbon $endsAt,
        string $scope = 'all',
        ?array $restrictedTo = null,
        ?string $reason = null,
        ?string $reasonDescription = null,
        ?string $decreeNumber = null,
        ?Carbon $decreeDate = null,
    ): UserDelegation {
        // Validation
        if ($delegator->id === $delegate->id) {
            throw IdentityException::cannotDelegateToSelf();
        }

        if ($endsAt->lte($startsAt)) {
            throw new IdentityException('End time must be after start time.');
        }

        if (!$delegator->canLogin()) {
            throw new IdentityException('Cannot delegate from a user who cannot login.');
        }

        if (!$delegate->canLogin()) {
            throw new IdentityException('Cannot delegate to a user who cannot login.');
        }

        // بررسی overlap
        $hasOverlap = UserDelegation::query()
            ->where('delegator_user_id', $delegator->id)
            ->where('status', 'active')
            ->where('scope', $scope)
            ->where(function ($q) use ($startsAt, $endsAt) {
                $q->whereBetween('starts_at', [$startsAt, $endsAt])
                  ->orWhereBetween('ends_at', [$startsAt, $endsAt])
                  ->orWhere(function ($q2) use ($startsAt, $endsAt) {
                      $q2->where('starts_at', '<=', $startsAt)
                         ->where('ends_at', '>=', $endsAt);
                  });
            })
            ->exists();

        if ($hasOverlap) {
            throw IdentityException::delegationOverlap();
        }

        return DB::transaction(function () use (
            $delegator, $delegate, $startsAt, $endsAt, $scope,
            $restrictedTo, $reason, $reasonDescription, $decreeNumber, $decreeDate
        ) {
            $now = now();
            $status = $startsAt->lte($now) ? 'active' : 'pending';

            $delegation = UserDelegation::create([
                'delegator_user_id' => $delegator->id,
                'delegate_user_id' => $delegate->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'scope' => $scope,
                'restricted_to' => $restrictedTo,
                'reason' => $reason,
                'reason_description' => $reasonDescription,
                'status' => $status,
                'decree_number' => $decreeNumber,
                'decree_date' => $decreeDate,
                'created_by' => auth()->id(),
            ]);

            $this->auditService->log(
                event: 'delegation_created',
                auditable: $delegation,
                description: "تفویض اختیار از '{$delegator->username}' به '{$delegate->username}' (scope: {$scope})",
                newValues: [
                    'delegator_user_id' => $delegator->id,
                    'delegate_user_id' => $delegate->id,
                    'scope' => $scope,
                    'starts_at' => $startsAt->toIso8601String(),
                    'ends_at' => $endsAt->toIso8601String(),
                ],
                severity: 'notice',
            );

            return $delegation->fresh();
        });
    }
}
