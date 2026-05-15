<?php

declare(strict_types=1);

namespace App\Domains\Reports\DTOs;

/**
 * ReportResult — خروجی استاندارد یک گزارش.
 *
 * - rows: ردیف‌های داده (آرایه‌ای از آرایه‌های associative)
 * - columns: تعریف ستون‌ها (key/label/type/extra)
 * - summary: شاخص‌های خلاصه (scalar یا آرایه)
 * - charts: تعریف نمودارها
 * - meta: متادیتای دلخواه (بازه زمانی، فیلترها، ...)
 */
class ReportResult
{
    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, array<string, mixed>> $columns
     * @param array<string, mixed> $summary
     * @param array<int, array<string, mixed>> $charts
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public readonly array $rows = [],
        public readonly array $columns = [],
        public readonly array $summary = [],
        public readonly array $charts = [],
        public readonly array $meta = [],
    ) {
    }

    /**
     * Helper برای ساخت تعریف یک ستون.
     *
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    public static function column(string $key, string $label, string $type = 'string', array $extra = []): array
    {
        return array_merge([
            'key' => $key,
            'label' => $label,
            'type' => $type,
        ], $extra);
    }

    /**
     * تعداد ردیف‌های داده.
     */
    public function rowCount(): int
    {
        return count($this->rows);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'rows' => $this->rows,
            'columns' => $this->columns,
            'summary' => $this->summary,
            'charts' => $this->charts,
            'meta' => $this->meta,
        ];
    }
}
