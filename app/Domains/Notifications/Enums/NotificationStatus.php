<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Enums;

enum NotificationStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Opened = 'opened';
    case Clicked = 'clicked';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'در انتظار ارسال',
            self::Sent => 'ارسال شد',
            self::Delivered => 'تحویل شد',
            self::Opened => 'خوانده شد',
            self::Clicked => 'کلیک شد',
            self::Failed => 'ناموفق',
            self::Cancelled => 'لغو شد',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Sent => 'info',
            self::Delivered => 'primary',
            self::Opened => 'success',
            self::Clicked => 'success',
            self::Failed => 'danger',
            self::Cancelled => 'gray',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Failed, self::Cancelled, self::Clicked], true);
    }

    public function canRetry(): bool
    {
        return $this === self::Failed;
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $s) => [$s->value => $s->label()])
            ->toArray();
    }
}
