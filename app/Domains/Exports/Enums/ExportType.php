<?php

declare(strict_types=1);

namespace App\Domains\Exports\Enums;

use Filament\Support\Contracts\HasLabel;

enum ExportType: string implements HasLabel
{
    case Meetings = 'meetings';
    case Minutes = 'minutes';
    case Resolutions = 'resolutions';
    case Tasks = 'tasks';
    case CalendarIcs = 'calendar_ics';
    case AuditLog = 'audit_log';
    case CustomReport = 'custom_report';
    case Users = 'users';

    public function label(): string
    {
        return match ($this) {
            self::Meetings => 'جلسات',
            self::Minutes => 'صورتجلسات',
            self::Resolutions => 'مصوبات',
            self::Tasks => 'وظایف',
            self::CalendarIcs => 'تقویم (ICS)',
            self::AuditLog => 'لاگ ممیزی',
            self::CustomReport => 'گزارش سفارشی',
            self::Users => 'کاربران',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
