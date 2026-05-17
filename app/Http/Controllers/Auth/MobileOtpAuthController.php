<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\OtpService;
use App\Http\Controllers\Controller;
use App\Support\Mobile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * ورود با موبایل و کد یک‌بارمصرف (OTP).
 */
class MobileOtpAuthController extends Controller
{
    private const SESSION_MOBILE = 'otp_login_mobile';

    public function showLogin(): View
    {
        return view('auth.mobile-login');
    }

    public function requestOtp(Request $request, OtpService $otp): RedirectResponse
    {
        $request->validate(['mobile' => ['required', 'string', 'max:20']]);

        $mobile = Mobile::normalize($request->input('mobile'));

        if ($mobile === null) {
            return back()->withErrors(['mobile' => 'شمارهٔ موبایل معتبر نیست.'])->withInput();
        }

        $user = User::query()->where('mobile', $mobile)->first();

        if ($user === null || ! $user->canLogin()) {
            return back()->withErrors([
                'mobile' => 'حساب فعالی با این شماره در سامانه یافت نشد.',
            ])->withInput();
        }

        try {
            $otp->send($mobile, $user->id, $request->ip());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['mobile' => $e->getMessage()])->withInput();
        }

        $request->session()->put(self::SESSION_MOBILE, $mobile);

        return redirect()->route('auth.otp.show');
    }

    public function showOtp(Request $request): RedirectResponse|View
    {
        $mobile = $request->session()->get(self::SESSION_MOBILE);

        if ($mobile === null) {
            return redirect()->route('auth.mobile.show');
        }

        return view('auth.otp-verify', ['mobile' => $mobile]);
    }

    public function verifyOtp(Request $request, OtpService $otp): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:8']]);

        $mobile = $request->session()->get(self::SESSION_MOBILE);

        if ($mobile === null) {
            return redirect()->route('auth.mobile.show');
        }

        if (! $otp->verify($mobile, trim((string) $request->input('code')))) {
            return back()->withErrors(['code' => 'کد واردشده نادرست یا منقضی است.']);
        }

        $user = User::query()->where('mobile', $mobile)->first();

        if ($user === null || ! $user->canLogin()) {
            return redirect()->route('auth.mobile.show')
                ->withErrors(['mobile' => 'امکان ورود با این حساب وجود ندارد.']);
        }

        Auth::login($user, remember: true);

        $user->forceFill([
            'mobile_verified_at' => $user->mobile_verified_at ?? now(),
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        $request->session()->forget(self::SESSION_MOBILE);
        $request->session()->regenerate();

        return redirect()->intended('/admin');
    }

    public function resendOtp(Request $request, OtpService $otp): RedirectResponse
    {
        $mobile = $request->session()->get(self::SESSION_MOBILE);

        if ($mobile === null) {
            return redirect()->route('auth.mobile.show');
        }

        $user = User::query()->where('mobile', $mobile)->first();

        try {
            $otp->send($mobile, $user?->id, $request->ip());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['code' => $e->getMessage()]);
        }

        return back()->with('status', 'کد جدید ارسال شد.');
    }
}
