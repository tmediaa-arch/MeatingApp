<?php

declare(strict_types=1);

namespace App\Domains\Reports\DTOs;

use Carbon\CarbonImmutable;

/**
 * ReportInput — پارامترهای ورودی اجرای یک گزارش.
 *
 * date_from / date_to / organization_id فیلدهای پرکاربردِ صریح هستند؛
 * بقیه پارامترها در $params نگهداری می‌شوند و با get() خوانده می‌شوند.
 */
class ReportInput
{
    /**
     * @param array<string, mixed> $params
     */
    public function __construct(
        public readonly ?CarbonImmutable $dateFrom = null,
        public readonly ?CarbonImmutable $dateTo = null,
        public readonly ?int $organizationId = null,
        public readonly array $params = [],
    ) {
    }

    /**
     * ساخت از آرایه (معمولاً ورودی فرم یا API).
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            dateFrom: self::parseDate($data['date_from'] ?? null),
            dateTo: self::parseDate($data['date_to'] ?? null),
            organizationId: isset($data['organization_id']) && $data['organization_id'] !== ''
                ? (int) $data['organization_id']
                : null,
            params: $data,
        );
    }

    /**
     * خواندن یک پارامتر دلخواه.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->params[$key] ?? $default;

        return $value === '' ? $default : $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_merge($this->params, [
            'date_from' => $this->dateFrom?->toDateString(),
            'date_to' => $this->dateTo?->toDateString(),
            'organization_id' => $this->organizationId,
        ]);
    }

    /**
     * hash پایدار برای cache lookup.
     */
    public function hash(): string
    {
        $normalized = $this->toArray();
        ksort($normalized);

        return hash('sha256', json_encode($normalized, JSON_UNESCAPED_UNICODE));
    }

    private static function parseDate(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
