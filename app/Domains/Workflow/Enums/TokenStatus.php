<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Enums;

enum TokenStatus: string
{
    case Active = 'active';
    case Waiting = 'waiting';
    case Consumed = 'consumed';
    case Cancelled = 'cancelled';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'فعال',
            self::Waiting => 'منتظر',
            self::Consumed => 'مصرف شده',
            self::Cancelled => 'لغو',
            self::Completed => 'تکمیل',
        };
    }

    public function isAlive(): bool
    {
        return in_array($this, [self::Active, self::Waiting], true);
    }

    public function isDead(): bool
    {
        return !$this->isAlive();
    }
}
