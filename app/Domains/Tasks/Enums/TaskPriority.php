<?php
declare(strict_types=1);
namespace App\Domains\Tasks\Enums;

enum TaskPriority: string
{
    case Critical = 'critical';
    case High = 'high';
    case Normal = 'normal';
    case Low = 'low';

    public function label(): string
    {
        return match ($this) {
            self::Critical => 'بحرانی',
            self::High => 'بالا',
            self::Normal => 'عادی',
            self::Low => 'پایین',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Critical => 'danger',
            self::High => 'warning',
            self::Normal => 'gray',
            self::Low => 'info',
        };
    }

    public function weight(): int
    {
        return match ($this) {
            self::Critical => 4,
            self::High => 3,
            self::Normal => 2,
            self::Low => 1,
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $s) => [$s->value => $s->label()])
            ->toArray();
    }
}
