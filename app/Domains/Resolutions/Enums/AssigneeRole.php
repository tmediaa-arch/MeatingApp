<?php
declare(strict_types=1);
namespace App\Domains\Resolutions\Enums;

enum AssigneeRole: string
{
    case Executor = 'executor';        // مجری
    case Supervisor = 'supervisor';    // ناظر
    case Beneficiary = 'beneficiary';  // ذی‌نفع
    case Observer = 'observer';        // ناظر اطلاعاتی

    public function label(): string
    {
        return match ($this) {
            self::Executor => 'مجری',
            self::Supervisor => 'ناظر',
            self::Beneficiary => 'ذی‌نفع',
            self::Observer => 'ناظر اطلاعاتی',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $s) => [$s->value => $s->label()])
            ->toArray();
    }
}
