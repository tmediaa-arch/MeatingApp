<?php

declare(strict_types=1);

namespace App\Domains\ServiceRequests\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ServiceRequestStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'پیش‌نویس',
            self::Submitted => 'ارسال شده',
            self::UnderReview => 'در حال بررسی',
            self::Approved => 'تأیید شده',
            self::Rejected => 'رد شده',
            self::InProgress => 'در حال انجام',
            self::Completed => 'انجام شده',
            self::Cancelled => 'لغو شده',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Submitted => 'info',
            self::UnderReview => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::InProgress => 'info',
            self::Completed => 'success',
            self::Cancelled => 'gray',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Rejected, self::Cancelled], true);
    }

    public function isOpen(): bool
    {
        return !$this->isTerminal();
    }

    public function canTransitionTo(self $new): bool
    {
        if ($this->isTerminal()) return false;

        return match ($this) {
            self::Draft => in_array($new, [self::Submitted, self::Cancelled], true),
            self::Submitted => in_array($new, [self::UnderReview, self::Approved, self::Rejected, self::Cancelled], true),
            self::UnderReview => in_array($new, [self::Approved, self::Rejected, self::Cancelled], true),
            self::Approved => in_array($new, [self::InProgress, self::Cancelled, self::Completed], true),
            self::InProgress => in_array($new, [self::Completed, self::Cancelled], true),
            default => false,
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($s) => [$s->value => $s->label()])
            ->toArray();
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string|array|null
    {
        return $this->color();
    }
}
