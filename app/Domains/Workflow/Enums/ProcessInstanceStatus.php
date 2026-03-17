<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Enums;

enum ProcessInstanceStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Suspended = 'suspended';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'در انتظار',
            self::Running => 'در حال اجرا',
            self::Suspended => 'متوقف',
            self::Completed => 'تکمیل شده',
            self::Cancelled => 'لغو شده',
            self::Failed => 'با خطا متوقف',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Running => 'info',
            self::Suspended => 'warning',
            self::Completed => 'success',
            self::Cancelled => 'gray',
            self::Failed => 'danger',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled, self::Failed], true);
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Pending, self::Running], true);
    }

    public function canTransitionTo(self $new): bool
    {
        if ($this->isTerminal()) return false;

        return match ($this) {
            self::Pending => in_array($new, [self::Running, self::Cancelled, self::Failed], true),
            self::Running => in_array($new, [self::Suspended, self::Completed, self::Cancelled, self::Failed], true),
            self::Suspended => in_array($new, [self::Running, self::Cancelled, self::Failed], true),
            default => false,
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($s) => [$s->value => $s->label()])
            ->toArray();
    }
}
