<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Domains\Identity\Models\UserInvitation;
use App\Domains\Identity\Services\OtpService;
use App\Domains\Identity\Services\UserInvitationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * پذیرش لینک دعوت — یافتن/ساخت حساب و ارسال کد ورود.
 */
class InviteController extends Controller
{
    private const SESSION_MOBILE = 'otp_login_mobile';

    public function accept(
        string $token,
        Request $request,
        UserInvitationService $invitations,
        OtpService $otp,
    ): RedirectResponse|View {
        $invitation = UserInvitation::query()->where('token', $token)->first();

        if ($invitation === null || $invitation->isExpired()) {
            return view('auth.invite-invalid');
        }

        // یافتن یا ساخت حساب کاربر مرتبط با شمارهٔ دعوت
        $user = $invitations->resolveUser($invitation);

        if (! $user->canLogin()) {
            return view('auth.invite-invalid', [
                'reason' => 'حساب مرتبط با این دعوت فعال نیست. با مدیر سامانه تماس بگیرید.',
            ]);
        }

        try {
            $otp->send($user->mobile, $user->id, $request->ip());
        } catch (\RuntimeException $e) {
            // محدودیت ارسال — کاربر می‌تواند از صفحهٔ کد، ارسال مجدد بزند
            report($e);
        }

        $request->session()->put(self::SESSION_MOBILE, $user->mobile);

        return redirect()->route('auth.otp.show');
    }
}
