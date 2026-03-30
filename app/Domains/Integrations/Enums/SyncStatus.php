<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Enums;

enum SyncStatus: string
{
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Partial = 'partial';

    public function label(): string
    {
        return match ($this) {
            self::Running => 'در حال اجرا',
            self::Completed => 'تکمیل شده',
            self::Failed => 'ناموفق',
            self::Partial => 'جزئی',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Running => 'info',
            self::Completed => 'success',
            self::Failed => 'danger',
            self::Partial => 'warning',
        };
    }
}
