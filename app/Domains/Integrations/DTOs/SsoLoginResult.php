<?php

declare(strict_types=1);

namespace App\Domains\Integrations\DTOs;

/**
 * SsoLoginResult — نتیجه پردازش callback از یک IdP در جریان SSO.
 */
final class SsoLoginResult
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        public readonly string $sessionId,
        public readonly string $nameId,
        public readonly ?string $nameIdFormat = null,
        public readonly ?string $email = null,
        public readonly ?string $displayName = null,
        public readonly array $attributes = [],
        public readonly ?\DateTimeInterface $expiresAt = null,
    ) {
    }
}
