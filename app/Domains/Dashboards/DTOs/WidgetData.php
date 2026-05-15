<?php

declare(strict_types=1);

namespace App\Domains\Dashboards\DTOs;

use Illuminate\Support\Collection;

/**
 * WidgetData — خروجی استاندارد یک widget داشبورد.
 *
 * سه نوع پشتیبانی می‌شود:
 * - stat: یک عدد/مقدار کلیدی با برچسب و رنگ
 * - chart: داده نمودار با نوع مشخص
 * - list: فهرستی از آیتم‌ها
 */
class WidgetData
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly string $type,
        public readonly array $payload = [],
    ) {
    }

    /**
     * widget از نوع آماری (یک مقدار کلیدی).
     */
    public static function stat(
        string $label,
        int|float|string $value,
        string $unit = '',
        string $color = 'primary',
    ): self {
        return new self('stat', [
            'label' => $label,
            'value' => $value,
            'unit' => $unit,
            'color' => $color,
        ]);
    }

    /**
     * widget از نوع نمودار.
     *
     * @param array<string|int, mixed> $data
     */
    public static function chart(string $chartType, array $data, string $title = ''): self
    {
        return new self('chart', [
            'chart_type' => $chartType,
            'data' => $data,
            'title' => $title,
        ]);
    }

    /**
     * widget از نوع فهرست.
     *
     * @param iterable<int, mixed> $items
     */
    public static function list(iterable $items, string $title = ''): self
    {
        $normalized = $items instanceof Collection
            ? $items->all()
            : (is_array($items) ? $items : iterator_to_array($items));

        return new self('list', [
            'items' => array_values($normalized),
            'title' => $title,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_merge(['type' => $this->type], $this->payload);
    }
}
