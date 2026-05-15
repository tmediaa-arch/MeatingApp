<?php

declare(strict_types=1);

namespace App\Domains\Integrations\DTOs;

/**
 * HrsEmployee — نمایش یک کارمند دریافت‌شده از سامانه منابع انسانی (HRS).
 */
final class HrsEmployee
{
    /**
     * @param array<string, mixed> $rawData
     */
    public function __construct(
        public readonly string $employeeNumber,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly ?string $nationalId = null,
        public readonly ?string $email = null,
        public readonly ?string $phone = null,
        public readonly ?string $departmentCode = null,
        public readonly ?string $departmentName = null,
        public readonly ?string $positionCode = null,
        public readonly ?string $positionTitle = null,
        public readonly ?string $managerEmployeeNumber = null,
        public readonly bool $isActive = true,
        public readonly ?\DateTimeInterface $hireDate = null,
        public readonly ?\DateTimeInterface $terminationDate = null,
        public readonly array $rawData = [],
    ) {
    }

    public function fullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }
}
