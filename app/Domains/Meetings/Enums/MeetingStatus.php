<?php

declare(strict_types=1);

namespace App\Domains\Meetings\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * چرخه عمر جلسه — state machine
 *
 * انتقال‌های مجاز:
 *   Draft → Scheduled → InvitationsSent → InProgress → Completed
 *           ↓             ↓                ↓
 *        Cancelled      Cancelled         Paused → InProgress
 *           ↓             ↓                Cancelled
 *        Postponed      Postponed         Completed
 *
 * Draft: قابل ویرایش کامل، عدم اطلاع به مدعوین
 * Scheduled: نهایی‌شده، آماده ارسال دعوت‌نامه‌ها
 * InvitationsSent: دعوت‌نامه‌ها رفته، در حال جمع‌آوری پاسخ
 * InProgress: جلسه در حال برگزاری است
 * Paused: توقف موقت (استراحت طولانی، تأخیر مدعوین)
 * Completed: جلسه برگزار شد، آماده نگارش صورتجلسه
 * Cancelled: لغو شده با ذکر دلیل
 * Postponed: به تعویق افتاد، انتظار زمان جدید
 */
enum MeetingStatus: string implements HasColor, HasIcon, HasLabel
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case InvitationsSent = 'invitations_sent';
    case InProgress = 'in_progress';
    case Paused = 'paused';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Postponed = 'postponed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'پیش‌نویس',
            self::Scheduled => 'برنامه‌ریزی شده',
            self::InvitationsSent => 'دعوت‌نامه‌ها ارسال شد',
            self::InProgress => 'در حال برگزاری',
            self::Paused => 'متوقف موقت',
            self::Completed => 'برگزار شد',
            self::Cancelled => 'لغو شد',
            self::Postponed => 'به تعویق افتاد',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Scheduled => 'blue',
            self::InvitationsSent => 'cyan',
            self::InProgress => 'success',
            self::Paused => 'warning',
            self::Completed => 'gray',
            self::Cancelled => 'danger',
            self::Postponed => 'warning',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Draft => 'heroicon-o-pencil-square',
            self::Scheduled => 'heroicon-o-calendar',
            self::InvitationsSent => 'heroicon-o-paper-airplane',
            self::InProgress => 'heroicon-o-play',
            self::Paused => 'heroicon-o-pause',
            self::Completed => 'heroicon-o-check-circle',
            self::Cancelled => 'heroicon-o-x-circle',
            self::Postponed => 'heroicon-o-clock',
        };
    }

    /**
     * انتقال‌های مجاز از هر وضعیت
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [
                self::Scheduled,
                self::Cancelled,
            ],
            self::Scheduled => [
                self::Draft,           // بازگشت برای ویرایش
                self::InvitationsSent,
                self::InProgress,      // در صورت skip ارسال دعوت‌نامه
                self::Cancelled,
                self::Postponed,
            ],
            self::InvitationsSent => [
                self::Scheduled,       // ابطال دعوت‌نامه‌ها و تغییر
                self::InProgress,
                self::Cancelled,
                self::Postponed,
            ],
            self::InProgress => [
                self::Paused,
                self::Completed,
                self::Cancelled,       // جلسه نیمه‌کاره ابطال
            ],
            self::Paused => [
                self::InProgress,
                self::Completed,       // پایان زودهنگام
                self::Cancelled,
            ],
            self::Completed => [],     // پایانی
            self::Cancelled => [],     // پایانی
            self::Postponed => [
                self::Scheduled,       // پس از تعیین زمان جدید
                self::Cancelled,
            ],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }

    public function isActive(): bool
    {
        return in_array($this, [
            self::InvitationsSent,
            self::InProgress,
            self::Paused,
        ], true);
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Scheduled], true);
    }

    /**
     * آیا با این وضعیت می‌توان وارد جلسه شد و حضور ثبت کرد؟
     */
    public function allowsAttendance(): bool
    {
        return in_array($this, [self::InProgress, self::Paused], true);
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($c) => [$c->value => $c->label()])
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
