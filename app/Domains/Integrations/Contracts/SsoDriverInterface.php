<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Contracts;

use App\Domains\Integrations\DTOs\SsoLoginResult;
use Symfony\Component\HttpFoundation\RedirectResponse;

interface SsoDriverInterface extends IntegrationDriverInterface
{
    /**
     * شروع SSO redirect — کاربر را به IdP می‌فرستد
     */
    public function initiateLogin(?string $returnUrl = null): RedirectResponse;

    /**
     * Process کردن callback از IdP و بازگرداندن کاربر
     */
    public function handleCallback(array $request): SsoLoginResult;

    /**
     * SLO — Single Logout
     */
    public function initiateLogout(string $sessionId, ?string $returnUrl = null): RedirectResponse;

    /**
     * متادیتای SP که باید به IdP داده شود
     */
    public function getServiceProviderMetadata(): string;
}
