<?php

declare(strict_types=1);

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\UserDelegation;
use Illuminate\Support\Facades\Session;

/**
 * Class DelegationContextService
 *
 * مدیریت "حالت نمایندگی" در session:
 * زمانی که کاربر A به نمایندگی از کاربر B (که به او تفویض کرده) اقدام می‌کند،
 * این Service تنظیم می‌کند که در سطح session آن delegation فعال است
 * و تمام audit log ها هم on_behalf_of_user_id را ثبت می‌کنند.
 *
 * UI: کاربر در navbar یک dropdown می‌بیند با گزینه‌های:
 *   - خودم
 *   - به نمایندگی از کاربر X (تفویض ۱)
 *   - به نمایندگی از کاربر Y (تفویض ۲)
 *
 * این پیاده‌سازی فاز ۱ ساده است؛ در فازهای بعد می‌توان آن را با
 * Redis-backed token-based context جایگزین کرد.
 */
class DelegationContextService
{
    private const SESSION_KEY_DELEGATION = 'active_delegation_id';
    private const SESSION_KEY_ON_BEHALF = 'on_behalf_of_user_id';

    /**
     * فعال کردن حالت نمایندگی
     */
    public function startActingOnBehalfOf(User $delegate, UserDelegation $delegation): void
    {
        // اعتبارسنجی
        if ($delegation->delegate_user_id !== $delegate->id) {
            throw new \InvalidArgumentException(
                'این تفویض متعلق به کاربر فعلی نیست.'
            );
        }

        if (!$delegation->isActive()) {
            throw new \InvalidArgumentException(
                'این تفویض در حال حاضر فعال نیست.'
            );
        }

        Session::put(self::SESSION_KEY_DELEGATION, $delegation->id);
        Session::put(self::SESSION_KEY_ON_BEHALF, $delegation->delegator_user_id);

        // افزایش شمارنده استفاده
        $delegation->increment('actions_count');
        $delegation->forceFill(['last_used_at' => now()])->saveQuietly();
    }

    /**
     * خروج از حالت نمایندگی
     */
    public function stopActingOnBehalfOf(): void
    {
        Session::forget([self::SESSION_KEY_DELEGATION, self::SESSION_KEY_ON_BEHALF]);
    }

    /**
     * آیا کاربر فعلی در حال اقدام به نمایندگی است؟
     */
    public function isActingOnBehalfOf(): bool
    {
        return Session::has(self::SESSION_KEY_DELEGATION);
    }

    public function getActiveDelegation(): ?UserDelegation
    {
        $id = Session::get(self::SESSION_KEY_DELEGATION);
        return $id ? UserDelegation::find($id) : null;
    }

    public function getOnBehalfOfUserId(): ?int
    {
        return Session::get(self::SESSION_KEY_ON_BEHALF);
    }

    /**
     * دریافت لیست تفویض‌های فعال که کاربر می‌تواند به نمایندگی از آن‌ها اقدام کند
     */
    public function getAvailableDelegationsFor(User $user)
    {
        return UserDelegation::query()
            ->where('delegate_user_id', $user->id)
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->with('delegator')
            ->get();
    }
}
