<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Adapters;

use App\Domains\Identity\Models\User;
use App\Domains\Integrations\Contracts\LdapDriverInterface;
use App\Domains\Integrations\DTOs\HealthCheckResult;
use App\Domains\Integrations\DTOs\LdapUser;
use App\Domains\Integrations\DTOs\SyncResult;
use App\Domains\Integrations\Models\LdapUserMapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\Models\ActiveDirectory\User as AdUser;

/**
 * LdapActiveDirectoryAdapter — اتصال به Active Directory مایکروسافت.
 *
 * استفاده از کتابخانه directorytree/ldaprecord-laravel.
 *
 * نگاشت پیش‌فرض attribute ها:
 * - sAMAccountName → uid
 * - displayName → name
 * - mail → email
 * - objectGUID → guid
 * - department → department
 * - title → title
 * - telephoneNumber → phone
 * - userAccountControl → is_disabled (bit 1)
 * - memberOf → groups
 */
class LdapActiveDirectoryAdapter extends AbstractIntegrationAdapter implements LdapDriverInterface
{
    public function getName(): string
    {
        return 'Active Directory';
    }

    public function checkHealth(): HealthCheckResult
    {
        $start = microtime(true);
        try {
            $connection = $this->makeConnection();
            $connection->connect();
            $latencyMs = (int) ((microtime(true) - $start) * 1000);

            return HealthCheckResult::healthy(
                message: 'اتصال LDAP موفق',
                latencyMs: $latencyMs,
                details: ['host' => $this->config('host')],
            );
        } catch (\Throwable $e) {
            return HealthCheckResult::down(
                message: 'اتصال LDAP ناموفق: ' . $e->getMessage(),
                details: ['error' => substr($e->getMessage(), 0, 500)],
            );
        }
    }

    public function authenticate(string $username, string $password): ?LdapUser
    {
        try {
            $connection = $this->makeConnection();
            Container::addConnection($connection, 'mms_runtime');

            $found = AdUser::on('mms_runtime')
                ->where('samaccountname', '=', $username)
                ->first();

            if (!$found) return null;

            $bound = $connection->auth()->attempt($found->getDn(), $password);
            if (!$bound) return null;

            return $this->mapToDto($found);
        } catch (\Throwable $e) {
            Log::warning('LDAP authentication failed', [
                'username' => $username,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function findUser(string $username): ?LdapUser
    {
        try {
            $connection = $this->makeConnection();
            Container::addConnection($connection, 'mms_runtime');

            $found = AdUser::on('mms_runtime')
                ->where('samaccountname', '=', $username)
                ->first();

            return $found ? $this->mapToDto($found) : null;
        } catch (\Throwable $e) {
            Log::warning('LDAP findUser failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function listAllUsers(): iterable
    {
        $connection = $this->makeConnection();
        Container::addConnection($connection, 'mms_runtime');

        $baseDn = $this->config('users_base_dn', $this->config('base_dn'));
        $filter = $this->config('user_filter', '(objectClass=user)');
        $pageSize = (int) $this->config('page_size', 500);

        foreach (AdUser::on('mms_runtime')
            ->in($baseDn)
            ->rawFilter($filter)
            ->paginate($pageSize) as $entry) {
            yield $this->mapToDto($entry);
        }
    }

    public function syncAllUsers(): SyncResult
    {
        $result = new SyncResult();

        $createMissing = (bool) $this->config('sync.create_missing_users', false);
        $disableMissing = (bool) $this->config('sync.disable_missing_users', true);

        try {
            foreach ($this->listAllUsers() as $ldapUser) {
                try {
                    $this->syncOneUser($ldapUser, $createMissing, $result);
                } catch (\Throwable $e) {
                    $result->recordError($ldapUser->uid, $e->getMessage());
                }
            }
        } catch (\Throwable $e) {
            $result->recordError('__ldap_traversal__', $e->getMessage());
        }

        return $result;
    }

    private function syncOneUser(LdapUser $ldapUser, bool $createMissing, SyncResult $result): void
    {
        $mapping = LdapUserMapping::query()
            ->where('provider_id', $this->provider->id)
            ->where('ldap_uid', $ldapUser->uid)
            ->first();

        DB::transaction(function () use ($ldapUser, $mapping, $createMissing, $result) {
            if ($mapping) {
                $mapping->fill([
                    'ldap_dn' => $ldapUser->dn,
                    'ldap_email' => $ldapUser->email,
                    'ldap_guid' => $ldapUser->guid,
                    'ldap_attributes' => $ldapUser->toArray(),
                    'last_synced_at' => now(),
                    'is_disabled_in_ldap' => $ldapUser->isDisabled,
                ])->save();

                $result->incrementUpdated();
                return;
            }

            // mapping ندارد، شاید کاربر را با email تطبیق دهیم
            $user = $ldapUser->email
                ? User::where('email', $ldapUser->email)->first()
                : null;

            if (!$user && $createMissing) {
                $user = User::create([
                    'name' => $ldapUser->name,
                    'email' => $ldapUser->email ?? $ldapUser->uid . '@ldap.local',
                    'password' => bcrypt(\Illuminate\Support\Str::random(32)),
                    'is_active' => !$ldapUser->isDisabled,
                ]);
                $result->incrementCreated();
            } elseif (!$user) {
                $result->incrementSkipped('کاربر داخلی پیدا نشد');
                return;
            } else {
                $result->incrementUpdated();
            }

            LdapUserMapping::create([
                'user_id' => $user->id,
                'provider_id' => $this->provider->id,
                'ldap_dn' => $ldapUser->dn,
                'ldap_uid' => $ldapUser->uid,
                'ldap_guid' => $ldapUser->guid,
                'ldap_email' => $ldapUser->email,
                'ldap_attributes' => $ldapUser->toArray(),
                'last_synced_at' => now(),
                'is_disabled_in_ldap' => $ldapUser->isDisabled,
            ]);
        });
    }

    private function makeConnection(): Connection
    {
        return new Connection([
            'hosts' => (array) $this->config('hosts', [$this->config('host')]),
            'base_dn' => $this->config('base_dn'),
            'username' => $this->config('username'),
            'password' => $this->config('password'),
            'port' => (int) $this->config('port', 389),
            'use_ssl' => (bool) $this->config('use_ssl', false),
            'use_tls' => (bool) $this->config('use_tls', false),
            'timeout' => (int) $this->config('timeout', 5),
            'version' => 3,
            'options' => $this->config('options', []),
        ]);
    }

    private function mapToDto(AdUser $entry): LdapUser
    {
        $uac = (int) ($entry->getFirstAttribute('useraccountcontrol') ?? 0);
        $isDisabled = (bool) ($uac & 2); // ACCOUNTDISABLE flag

        $memberOf = $entry->getAttribute('memberof') ?? [];

        return new LdapUser(
            dn: $entry->getDn(),
            uid: (string) $entry->getFirstAttribute('samaccountname'),
            name: (string) ($entry->getFirstAttribute('displayname')
                ?? $entry->getFirstAttribute('cn')
                ?? $entry->getFirstAttribute('samaccountname')),
            email: $entry->getFirstAttribute('mail'),
            guid: $entry->getConvertedGuid(),
            department: $entry->getFirstAttribute('department'),
            title: $entry->getFirstAttribute('title'),
            phone: $entry->getFirstAttribute('telephonenumber'),
            isDisabled: $isDisabled,
            groups: array_map(fn ($g) => (string) $g, (array) $memberOf),
            rawAttributes: $entry->getAttributes(),
        );
    }
}
