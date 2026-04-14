<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class EnforceMfa
 *
 * برای کاربرانی که در نقش‌های حساس هستند، MFA الزامی است.
 * اگر MFA فعال نباشد، آن‌ها به صفحه ست‌آپ MFA هدایت می‌شوند.
 * اگر MFA فعال است اما هنوز در این session تأیید نشده، به صفحه challenge می‌روند.
 *
 * (پیاده‌سازی کامل MFA Challenge در فاز ۱.۵ — اینجا فقط enforcement)
 */
class EnforceMfa
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (!$user) {
            return $next($request);
        }

        $mfaRequiredRoles = config('mms.identity.mfa_required_roles', []);

        if ($user->hasAnyRole($mfaRequiredRoles) && !$user->mfa_enabled) {
            // اگر کاربر قبلاً در حال راه‌اندازی MFA است، اجازه بده ادامه دهد
            if ($request->routeIs('mfa.setup*')) {
                return $next($request);
            }

            return redirect()->route('mfa.setup')->with(
                'warning',
                'فعال‌سازی احراز هویت دومرحله‌ای برای نقش شما الزامی است.'
            );
        }

        if ($user->mfa_enabled && !session('mfa_verified')) {
            if ($request->routeIs('mfa.challenge*', 'logout')) {
                return $next($request);
            }

            return redirect()->route('mfa.challenge');
        }

        return $next($request);
    }
}
