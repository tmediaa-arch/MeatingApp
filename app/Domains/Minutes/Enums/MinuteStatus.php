<?php

declare(strict_types=1);

namespace App\Domains\Minutes\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * چرخه عمر صورتجلسه.
 *
 * draft     → ابتدا توسط دبیر ایجاد می‌شود
 * review    → ارسال شد برای بازبینی توسط رئیس
 * signed    → امضای دبیر + رئیس کامل شد
 * published → نهایی و در دسترس همه دارای دسترسی
 * revoked   → ابطال شد (نیاز به مجوز ویژه)
 * archived  → بایگانی شد
 */
enum MinuteStatus: string implements HasColor, HasIcon, HasLabel
{
    case Draft = 'draft';
    case Review = 'review';
    case Signed = 'signed';
    case Published = 'published';
    case Revoked = 'revoked';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'پیش‌نویس',
            self::Review => 'در حال بازبینی',
            self::Signed => 'امضا شده',
            self::Published => 'منتشر شده',
            self::Revoked => 'ابطال شده',
            self::Archived => 'بایگانی شده',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Review => 'warning',
            self::Signed => 'info',
            self::Published => 'success',
            self::Revoked => 'danger',
            self::Archived => 'gray',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Draft => 'heroicon-o-pencil-square',
            self::Review => 'heroicon-o-eye',
            self::Signed => 'heroicon-o-finger-print',
            self::Published => 'heroicon-o-check-badge',
            self::Revoked => 'heroicon-o-x-circle',
            self::Archived => 'heroicon-o-archive-box',
        };
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Review], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Revoked, self::Archived], true);
    }

    /**
     * Transitions مجاز در state machine
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Review, self::Archived],
            self::Review => [self::Draft, self::Signed],
            self::Signed => [self::Published, self::Review],
            self::Published => [self::Revoked, self::Archived],
            self::Revoked => [self::Archived],
            self::Archived => [],
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
