<?php

declare(strict_types=1);

namespace App\Domains\Resolutions\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\Resolutions\Enums\ResolutionStatus;
use App\Domains\Resolutions\Models\Resolution;

class ResolutionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('resolution.view');
    }

    public function view(User $user, Resolution $resolution): bool
    {
        if ($user->hasPermissionTo('resolution.view_all')) return true;
        // اگر minute را می‌بیند، resolution هم
        return $resolution->minute->canBeViewedBy($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('resolution.create');
    }

    public function update(User $user, Resolution $resolution): bool
    {
        if (!$user->hasPermissionTo('resolution.update')) return false;
        return !$resolution->status->isTerminal();
    }

    public function delete(User $user, Resolution $resolution): bool
    {
        if (!$user->hasPermissionTo('resolution.delete')) return false;
        return $resolution->status === ResolutionStatus::Draft;
    }

    public function vote(User $user, Resolution $resolution): bool
    {
        if (!$resolution->requires_voting) return false;
        if (!$resolution->isVotingOpen()) return false;

        // فقط کارمندانی که در جلسه بودند می‌توانند رأی دهند
        return $resolution->meeting->participants()
            ->where('employee_id', $user->employee_id)
            ->exists();
    }

    public function closeVoting(User $user, Resolution $resolution): bool
    {
        return $user->hasPermissionTo('resolution.close-voting')
            && $resolution->isVotingOpen();
    }
}
