<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\UserDelegation;
use Illuminate\Support\Facades\DB;

/**
 * RevokeDelegationAction — لغو یک تفویض اختیار توسط delegator یا مدیر.
 */
class RevokeDelegationAction
{
    public function execute(UserDelegation $delegation, string $reason): UserDelegation
    {
        return DB::transaction(function () use ($delegation, $reason) {
            $delegation->update([
                'status' => 'revoked',
                'revoked_by' => auth()->id(),
                'revoked_at' => now(),
                'reason_description' => trim(
                    ($delegation->reason_description ? $delegation->reason_description . ' — ' : '')
                    . 'لغو شد: ' . $reason,
                ),
            ]);

            return $delegation->refresh();
        });
    }
}
