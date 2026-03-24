<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domains\Workflow\Models\ProcessIncident;
use App\Domains\Workflow\Models\UserTask;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OpenIncidentsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 51;

    protected function getStats(): array
    {
        $count = ProcessIncident::where('status', 'open')->count();
        $byType = ProcessIncident::where('status', 'open')
            ->selectRaw('incident_type, count(*) as c')
            ->groupBy('incident_type')
            ->pluck('c', 'incident_type')
            ->toArray();

        return [
            Stat::make('Incident باز', $count)
                ->description(empty($byType) ? '—' : 'پرتکرار: ' . array_key_first($byType))
                ->icon('heroicon-o-exclamation-circle')
                ->color($count > 0 ? 'danger' : 'success'),
            Stat::make('UserTask overdue', UserTask::overdue()->count())
                ->icon('heroicon-o-clock')
                ->color('danger'),
        ];
    }
}
