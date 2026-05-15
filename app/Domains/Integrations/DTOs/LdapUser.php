<?php

declare(strict_types=1);

namespace App\Domains\Integrations\DTOs;

/**
 * LdapUser — نمایش یک کاربر دریافت‌شده از LDAP/Active Directory.
 */
final class LdapUser
{
    /**
     * @param string[] $groups
     * @param array<string, mixed> $rawAttributes
     */
    public function __construct(
        public readonly string $dn,
        public readonly string $uid,
        public readonly string $name,
        public readonly ?string $email = null,
        public readonly ?string $guid = null,
        public readonly ?string $department = null,
        public readonly ?string $title = null,
        public readonly ?string $phone = null,
        public readonly bool $isDisabled = false,
        public readonly array $groups = [],
        public readonly array $rawAttributes = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'dn' => $this->dn,
            'uid' => $this->uid,
            'name' => $this->name,
            'email' => $this->email,
            'guid' => $this->guid,
            'department' => $this->department,
            'title' => $this->title,
            'phone' => $this->phone,
            'is_disabled' => $this->isDisabled,
            'groups' => $this->groups,
        ];
    }
}
