<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domains\Tasks\Services\TaskEscalationService;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OverdueTasksWidget extends StatsOverviewWidget
{
    public function __construct(private readonly TaskEscalationService $service)
    {
    }

    protected function getStats(): array
    {
        $stats = $this->service->getOverdueStats();

        return [
            Stat::make('کل وظایف تأخیردار', $stats['total_overdue'])
                ->description($stats['total_overdue'] > 0 ? 'نیاز به پیگیری' : 'هیچ تأخیر فعالی نیست')
                ->descriptionIcon(Heroicon::MiniExclamationTriangle)
                ->color($stats['total_overdue'] > 0 ? 'danger' : 'success'),

            Stat::make('سطح 1 (Supervisor)', $stats['level_1'])
                ->description('1+ روز تأخیر')
                ->color('warning'),

            Stat::make('سطح 2 (Approver)', $stats['level_2'])
                ->description('3+ روز تأخیر')
                ->color('warning'),

            Stat::make('سطح 3 (Critical)', $stats['level_3'])
                ->description('7+ روز تأخیر')
                ->color('danger'),
        ];
    }
}
