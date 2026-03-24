<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domains\Workflow\Enums\ProcessInstanceStatus;
use App\Domains\Workflow\Models\ProcessInstance;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ActiveInstancesWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 50;

    protected function getStats(): array
    {
        return [
            Stat::make('Instance در حال اجرا', ProcessInstance::where('status', ProcessInstanceStatus::Running)->count())
                ->icon('heroicon-o-play-circle')
                ->color('info'),
            Stat::make('Instance متوقف', ProcessInstance::where('status', ProcessInstanceStatus::Suspended)->count())
                ->icon('heroicon-o-pause-circle')
                ->color('warning'),
            Stat::make('SLA رد شده', ProcessInstance::slaBreached()->count())
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger'),
        ];
    }
}
