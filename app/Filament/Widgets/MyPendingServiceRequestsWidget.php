<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domains\ServiceRequests\Models\ServiceRequest;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MyPendingServiceRequestsWidget extends BaseWidget
{
    protected static ?int $sort = 30;

    protected function getStats(): array
    {
        $userId = auth()->id();

        $draft = ServiceRequest::where('requester_user_id', $userId)
            ->where('status', 'draft')
            ->count();

        $submitted = ServiceRequest::where('requester_user_id', $userId)
            ->whereIn('status', ['submitted', 'under_review'])
            ->count();

        $inProgress = ServiceRequest::where('requester_user_id', $userId)
            ->whereIn('status', ['approved', 'in_progress'])
            ->count();

        $overdue = ServiceRequest::where('requester_user_id', $userId)
            ->overdue()
            ->count();

        return [
            Stat::make('پیش‌نویس', $draft)
                ->icon('heroicon-o-pencil')
                ->color('gray'),
            Stat::make('در انتظار', $submitted)
                ->icon('heroicon-o-clock')
                ->color('warning'),
            Stat::make('در حال انجام', $inProgress)
                ->icon('heroicon-o-cog-6-tooth')
                ->color('info'),
            Stat::make('Overdue', $overdue)
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger'),
        ];
    }
}
