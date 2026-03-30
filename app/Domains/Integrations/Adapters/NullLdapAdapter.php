<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Adapters;

use App\Domains\Integrations\Contracts\LdapDriverInterface;
use App\Domains\Integrations\DTOs\HealthCheckResult;
use App\Domains\Integrations\DTOs\LdapUser;
use App\Domains\Integrations\DTOs\SyncResult;

/**
 * NullLdapAdapter — adapter ساختگی برای محیط dev/test.
 *
 * هیچ ارتباطی با LDAP واقعی برقرار نمی‌کند و یک کاربر تستی برمی‌گرداند.
 */
class NullLdapAdapter extends AbstractIntegrationAdapter implements LdapDriverInterface
{
    public function getName(): string
    {
        return 'Null LDAP (dev)';
    }

    public function checkHealth(): HealthCheckResult
    {
        return HealthCheckResult::healthy('null adapter — همیشه سالم');
    }

    public function authenticate(string $username, string $password): ?LdapUser
    {
        if ($password === 'wrong') return null;

        return new LdapUser(
            dn: "CN={$username},OU=Users,DC=example,DC=local",
            uid: $username,
            name: ucfirst($username) . ' User',
            email: "{$username}@example.local",
            guid: bin2hex(random_bytes(8)),
        );
    }

    public function findUser(string $username): ?LdapUser
    {
        return $this->authenticate($username, 'placeholder');
    }

    public function listAllUsers(): iterable
    {
        // فقط چند کاربر نمونه برای dev
        foreach (['alice', 'bob', 'carol'] as $u) {
            yield $this->findUser($u);
        }
    }

    public function syncAllUsers(): SyncResult
    {
        $r = new SyncResult();
        foreach ($this->listAllUsers() as $_) {
            $r->incrementSkipped('null adapter');
        }
        return $r;
    }
}
