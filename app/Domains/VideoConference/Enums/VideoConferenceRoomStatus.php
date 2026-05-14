<?php

declare(strict_types=1);

namespace App\Domains\VideoConference\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum VideoConferenceRoomStatus: string implements HasColor, HasLabel
{
    case Scheduled = 'scheduled';
    case Starting = 'starting';
    case InProgress = 'in_progress';
    case Ended = 'ended';
    case Cancelled = 'cancelled';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'برنامه‌ریزی شده',
            self::Starting => 'در حال شروع',
            self::InProgress => 'در حال برگزاری',
            self::Ended => 'پایان یافته',
            self::Cancelled => 'لغو شده',
            self::Failed => 'خطا',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Scheduled => 'gray',
            self::Starting => 'info',
            self::InProgress => 'success',
            self::Ended => 'gray',
            self::Cancelled => 'danger',
            self::Failed => 'danger',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Ended, self::Cancelled, self::Failed], true);
    }

    public function canTransitionTo(self $new): bool
    {
        if ($this->isTerminal()) return false;
        return match ($this) {
            self::Scheduled => in_array($new, [self::Starting, self::InProgress, self::Cancelled, self::Failed], true),
            self::Starting => in_array($new, [self::InProgress, self::Failed, self::Cancelled], true),
            self::InProgress => in_array($new, [self::Ended, self::Failed], true),
            default => false,
        };
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
