<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Enums;

enum UserTaskStatus: string
{
    case Created = 'created';
    case Assigned = 'assigned';
    case Claimed = 'claimed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Reassigned = 'reassigned';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'ایجاد شده',
            self::Assigned => 'ارجاع‌شده',
            self::Claimed => 'در دست انجام',
            self::Completed => 'تکمیل شده',
            self::Cancelled => 'لغو شده',
            self::Reassigned => 'منتقل شده',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Created => 'gray',
            self::Assigned => 'info',
            self::Claimed => 'warning',
            self::Completed => 'success',
            self::Cancelled => 'danger',
            self::Reassigned => 'gray',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Created, self::Assigned, self::Claimed], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled, self::Reassigned], true);
    }
}
