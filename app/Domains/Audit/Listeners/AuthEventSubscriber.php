<?php

declare(strict_types=1);

namespace App\Domains\Audit\Listeners;

use App\Domains\Audit\Models\LoginLog;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Events\Dispatcher;

/**
 * هر رویداد احراز هویت (ورود موفق، ورود ناموفق، قفل‌شدن، خروج) را در
 * جدول login_logs ثبت می‌کند. این subscriber در DomainServiceProvider
 * ثبت می‌شود و چون Filament از همان guard استاندارد استفاده می‌کند،
 * ورود از پنل ادمین را هم پوشش می‌دهد.
 */
class AuthEventSubscriber
{
    public function handleLogin(Login $event): void
    {
        LoginLog::create([
            'user_id' => $event->user->getAuthIdentifier(),
            'username_attempted' => $event->user->username ?? null,
            'result' => 'success',
            'auth_method' => 'password',
            'ip_address' => request()->ip(),
            'user_agent' => $this->userAgent(),
            'session_id' => $this->sessionId(),
            'performed_at' => now(),
        ]);
    }

    public function handleFailed(Failed $event): void
    {
        $username = $event->credentials['username']
            ?? $event->credentials['email']
            ?? null;

        LoginLog::create([
            'user_id' => $event->user?->getAuthIdentifier(),
            'username_attempted' => $username ? mb_substr((string) $username, 0, 200) : null,
            'result' => 'failed_credentials',
            'auth_method' => 'password',
            'ip_address' => request()->ip(),
            'user_agent' => $this->userAgent(),
            'session_id' => $this->sessionId(),
            'performed_at' => now(),
        ]);
    }

    public function handleLockout(Lockout $event): void
    {
        $username = $event->request->input('username') ?? $event->request->input('email');

        LoginLog::create([
            'username_attempted' => $username ? mb_substr((string) $username, 0, 200) : null,
            'result' => 'locked',
            'auth_method' => 'password',
            'ip_address' => $event->request->ip(),
            'user_agent' => mb_substr((string) $event->request->userAgent(), 0, 500),
            'session_id' => $this->sessionId(),
            'performed_at' => now(),
        ]);
    }

    public function handleLogout(Logout $event): void
    {
        if (! $event->user) {
            return;
        }

        LoginLog::query()
            ->where('user_id', $event->user->getAuthIdentifier())
            ->where('session_id', $this->sessionId())
            ->whereNull('logged_out_at')
            ->latest('performed_at')
            ->first()
            ?->update([
                'logged_out_at' => now(),
                'logout_reason' => 'user',
            ]);
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'handleLogin',
            Failed::class => 'handleFailed',
            Lockout::class => 'handleLockout',
            Logout::class => 'handleLogout',
        ];
    }

    private function userAgent(): ?string
    {
        $ua = request()->userAgent();

        return $ua ? mb_substr($ua, 0, 500) : null;
    }

    private function sessionId(): ?string
    {
        try {
            return request()->hasSession() ? request()->session()->getId() : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
