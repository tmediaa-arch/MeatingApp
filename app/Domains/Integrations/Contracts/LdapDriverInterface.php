<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Contracts;

use App\Domains\Integrations\DTOs\LdapUser;
use App\Domains\Integrations\DTOs\SyncResult;

interface LdapDriverInterface extends IntegrationDriverInterface
{
    /**
     * احراز هویت کاربر با LDAP — uid/password
     *
     * @return LdapUser|null null اگر اعتبارسنجی موفق نبود
     */
    public function authenticate(string $username, string $password): ?LdapUser;

    /**
     * جستجوی کاربر در LDAP
     */
    public function findUser(string $username): ?LdapUser;

    /**
     * فهرست همه کاربران LDAP (برای sync)
     *
     * @return iterable<LdapUser>
     */
    public function listAllUsers(): iterable;

    /**
     * sync کامل کاربران از LDAP به سامانه داخلی
     */
    public function syncAllUsers(): SyncResult;
}
