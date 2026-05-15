<?php

declare(strict_types=1);

namespace App\Domains\Tasks\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * چرخه عمر کامل وظیفه.
 *
 * Open → Assigned → InProgress → Submitted → UnderReview →
 *   Completed | NeedsRevision (→ InProgress) | Cancelled
 */
enum TaskStatus: string implements HasColor, HasIcon, HasLabel
{
    case Open = 'open';                  // ایجاد شد، هنوز ارجاع نشده
    case Assigned = 'assigned';          // ارجاع شد، کار شروع نشده
    case InProgress = 'in_progress';     // در حال انجام
    case Submitted = 'submitted';        // مجری گزارش پایان داده
    case UnderReview = 'under_review';   // در حال بررسی توسط تأییدکننده
    case NeedsRevision = 'needs_revision'; // نیاز به اصلاح
    case Completed = 'completed';        // تأیید نهایی
    case Cancelled = 'cancelled';        // لغو شد

    public function label(): string
    {
        return match ($this) {
            self::Open => 'باز',
            self::Assigned => 'ارجاع شد',
            self::InProgress => 'در حال انجام',
            self::Submitted => 'ارسال شد',
            self::UnderReview => 'در حال بررسی',
            self::NeedsRevision => 'نیاز به اصلاح',
            self::Completed => 'انجام شد',
            self::Cancelled => 'لغو شد',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'gray',
            self::Assigned => 'info',
            self::InProgress => 'primary',
            self::Submitted => 'warning',
            self::UnderReview => 'warning',
            self::NeedsRevision => 'danger',
            self::Completed => 'success',
            self::Cancelled => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Open => 'heroicon-o-inbox',
            self::Assigned => 'heroicon-o-user-plus',
            self::InProgress => 'heroicon-o-play',
            self::Submitted => 'heroicon-o-paper-airplane',
            self::UnderReview => 'heroicon-o-magnifying-glass',
            self::NeedsRevision => 'heroicon-o-arrow-uturn-left',
            self::Completed => 'heroicon-o-check-badge',
            self::Cancelled => 'heroicon-o-x-circle',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [
            self::Open, self::Assigned, self::InProgress,
            self::Submitted, self::UnderReview, self::NeedsRevision,
        ], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }

    public function isAssigned(): bool
    {
        return !in_array($this, [self::Open, self::Cancelled], true);
    }

    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Open => [self::Assigned, self::Cancelled],
            self::Assigned => [self::InProgress, self::Cancelled],
            self::InProgress => [self::Submitted, self::Cancelled],
            self::Submitted => [self::UnderReview, self::Completed, self::NeedsRevision],
            self::UnderReview => [self::Completed, self::NeedsRevision, self::Cancelled],
            self::NeedsRevision => [self::InProgress, self::Cancelled],
            self::Completed, self::Cancelled => [],
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

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string|array|null
    {
        return $this->color();
    }

    public function getIcon(): string|\BackedEnum|\Illuminate\Contracts\Support\Htmlable|null
    {
        return $this->icon();
    }
}
