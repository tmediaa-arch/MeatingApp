<?php

declare(strict_types=1);

namespace App\Domains\Reports\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ReportCategory: string implements HasIcon, HasLabel
{
    case Meetings = 'meetings';
    case Minutes = 'minutes';
    case Resolutions = 'resolutions';
    case Tasks = 'tasks';
    case Attendance = 'attendance';
    case Files = 'files';
    case Audit = 'audit';
    case Workflow = 'workflow';
    case VideoConference = 'video_conference';
    case Kpi = 'kpi';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Meetings => 'جلسات',
            self::Minutes => 'صورتجلسات',
            self::Resolutions => 'مصوبات',
            self::Tasks => 'وظایف',
            self::Attendance => 'حضور و غیاب',
            self::Files => 'فایل‌ها',
            self::Audit => 'ممیزی',
            self::Workflow => 'گردش کار',
            self::VideoConference => 'ویدئوکنفرانس',
            self::Kpi => 'شاخص‌های کلیدی',
            self::Custom => 'سفارشی',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Meetings => 'heroicon-o-calendar-days',
            self::Minutes => 'heroicon-o-document-text',
            self::Resolutions => 'heroicon-o-clipboard-document-check',
            self::Tasks => 'heroicon-o-clipboard-document-list',
            self::Attendance => 'heroicon-o-user-group',
            self::Files => 'heroicon-o-folder',
            self::Audit => 'heroicon-o-shield-check',
            self::Workflow => 'heroicon-o-arrow-path',
            self::VideoConference => 'heroicon-o-video-camera',
            self::Kpi => 'heroicon-o-chart-bar',
            self::Custom => 'heroicon-o-sparkles',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getIcon(): string|\BackedEnum|\Illuminate\Contracts\Support\Htmlable|null
    {
        return $this->icon();
    }
}
