<?php

declare(strict_types=1);

namespace App\Domains\Exports\Enums;

enum ExportStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'در صف',
            self::Processing => 'در حال پردازش',
            self::Completed => 'تکمیل شده',
            self::Failed => 'ناموفق',
            self::Expired => 'منقضی',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Queued => 'gray',
            self::Processing => 'info',
            self::Completed => 'success',
            self::Failed => 'danger',
            self::Expired => 'warning',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Failed, self::Expired], true);
    }
}
