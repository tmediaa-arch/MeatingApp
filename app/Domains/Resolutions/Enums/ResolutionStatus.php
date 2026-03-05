<?php

declare(strict_types=1);

namespace App\Domains\Resolutions\Enums;

enum ResolutionStatus: string
{
    case Draft = 'draft';
    case Voted = 'voted';        // رأی‌گیری انجام شد
    case Approved = 'approved';
    case InExecution = 'in_execution';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'پیش‌نویس',
            self::Voted => 'رأی‌گیری شده',
            self::Approved => 'تأیید شده',
            self::InExecution => 'در حال اجرا',
            self::Completed => 'اجرا شده',
            self::Cancelled => 'لغو شده',
            self::Failed => 'ناموفق',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Voted => 'info',
            self::Approved => 'success',
            self::InExecution => 'primary',
            self::Completed => 'success',
            self::Cancelled => 'danger',
            self::Failed => 'danger',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled, self::Failed], true);
    }

    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Voted, self::Approved, self::Cancelled],
            self::Voted => [self::Approved, self::Cancelled, self::Failed],
            self::Approved => [self::InExecution, self::Cancelled],
            self::InExecution => [self::Completed, self::Failed, self::Cancelled],
            self::Completed, self::Cancelled, self::Failed => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $s) => [$s->value => $s->label()])
            ->toArray();
    }
}
