<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domains\ServiceRequests\Models\ServiceRequest;
use Filament\Support\Icons\Heroicon;
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
                ->icon(Heroicon::OutlinedPencil)
                ->color('gray'),
            Stat::make('در انتظار', $submitted)
                ->icon(Heroicon::OutlinedClock)
                ->color('warning'),
            Stat::make('در حال انجام', $inProgress)
                ->icon(Heroicon::OutlinedCog6Tooth)
                ->color('info'),
            Stat::make('Overdue', $overdue)
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->color('danger'),
        ];
    }
}
