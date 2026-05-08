<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Services;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Identity\Models\User;
use App\Domains\Integrations\Contracts\SsoDriverInterface;
use App\Domains\Integrations\DTOs\SsoLoginResult;
use App\Domains\Integrations\Models\IntegrationProvider;
use App\Domains\Integrations\Models\SsoSession;

/**
 * SsoAuthService — مدیریت احراز هویت از طریق SSO.
 *
 * این سرویس:
 * 1. کاربر را با attribute های دریافت شده (معمولاً email) تطبیق می‌دهد
 * 2. اگر کاربر وجود ندارد و JIT provisioning فعال است، می‌سازد
 * 3. SsoSession را ذخیره می‌کند
 * 4. login می‌کند
 */
class SsoAuthService
{
    public function __construct(
        private readonly IntegrationProviderManager $manager,
        private readonly AuditService $auditService,
    ) {
    }

    public function handleCallback(IntegrationProvider $provider, array $request, ?string $ip = null, ?string $userAgent = null): User
    {
        $driver = $this->manager->resolve($provider);
        if (!$driver instanceof SsoDriverInterface) {
            throw new \LogicException('Provider SSO نیست.');
        }

        /** @var SsoLoginResult $result */
        $result = $driver->handleCallback($request);

        $email = $result->email;
        if (!$email) {
            throw new \DomainException('پاسخ SSO شامل ایمیل نیست.');
        }

        $user = User::where('email', $email)->first();
        $jitEnabled = (bool) $provider->getConfigValue('jit_provisioning', false);

        if (!$user && $jitEnabled) {
            $user = User::create([
                'name' => $result->displayName ?? $email,
                'email' => $email,
                'password' => bcrypt(\Illuminate\Support\Str::random(48)),
                'is_active' => true,
            ]);

            $this->auditService->log(
                event: 'user_jit_provisioned',
                auditable: $user,
                description: "کاربر «{$user->name}» از طریق SSO با JIT ساخته شد.",
                severity: 'info',
            );
        } elseif (!$user) {
            throw new \DomainException('کاربر در سامانه ثبت نشده است و JIT provisioning فعال نیست.');
        }

        SsoSession::create([
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'session_id' => $result->sessionId,
            'name_id' => $result->nameId,
            'name_id_format' => $result->nameIdFormat,
            'attributes' => $result->attributes,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'authenticated_at' => now(),
            'expires_at' => $result->expiresAt,
        ]);

        $this->auditService->log(
            event: 'user_sso_login',
            auditable: $user,
            description: "کاربر «{$user->name}» از طریق {$provider->display_name} وارد شد.",
            context: ['provider_id' => $provider->id, 'session_id' => $result->sessionId],
            severity: 'info',
        );

        return $user;
    }

    public function terminateSession(string $sessionId): void
    {
        SsoSession::where('session_id', $sessionId)
            ->whereNull('logged_out_at')
            ->update(['logged_out_at' => now()]);
    }
}
