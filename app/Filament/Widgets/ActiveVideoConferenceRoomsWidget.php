<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domains\VideoConference\Enums\HealthStatus;
use App\Domains\VideoConference\Models\VideoConferenceProvider;
use App\Domains\VideoConference\Models\VideoConferenceRoom;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ActiveVideoConferenceRoomsWidget extends BaseWidget
{
    protected static ?int $sort = 40;

    public static function canView(): bool
    {
        return auth()->user()?->can('vc_provider.view') ?? false;
    }

    protected function getStats(): array
    {
        $inProgress = VideoConferenceRoom::query()->inProgress()->count();
        $scheduledToday = VideoConferenceRoom::query()
            ->where('status', 'scheduled')
            ->whereDate('scheduled_start_at', now()->toDateString())
            ->count();
        $endedToday = VideoConferenceRoom::query()
            ->whereDate('actual_end_at', now()->toDateString())
            ->count();

        $unhealthyProviders = VideoConferenceProvider::query()
            ->active()
            ->where('health_status', HealthStatus::Unhealthy->value)
            ->count();

        return [
            Stat::make('در حال برگزاری', $inProgress)
                ->icon('heroicon-o-video-camera')
                ->color('success'),

            Stat::make('برنامه امروز', $scheduledToday)
                ->icon('heroicon-o-calendar')
                ->color('info'),

            Stat::make('پایان امروز', $endedToday)
                ->icon('heroicon-o-check-circle')
                ->color('gray'),

            Stat::make('Provider ناسالم', $unhealthyProviders)
                ->icon('heroicon-o-exclamation-triangle')
                ->color($unhealthyProviders > 0 ? 'danger' : 'success'),
        ];
    }
}
