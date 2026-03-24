<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domains\ServiceRequests\Models\ServiceRequest;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PendingReviewRequestsWidget extends BaseWidget
{
    protected static ?int $sort = 31;

    public static function canView(): bool
    {
        return auth()->user()?->can('service_request.review') ?? false;
    }

    protected function getStats(): array
    {
        $pending = ServiceRequest::pendingReview()->count();
        $highPriority = ServiceRequest::pendingReview()
            ->whereIn('priority', ['high', 'critical'])
            ->count();
        $overdueOpen = ServiceRequest::overdue()->count();

        return [
            Stat::make('در صف بررسی', $pending)
                ->icon('heroicon-o-queue-list')
                ->color('warning')
                ->url(route('filament.admin.pages.service-request-review')),

            Stat::make('اولویت بالا', $highPriority)
                ->icon('heroicon-o-fire')
                ->color('danger'),

            Stat::make('کل overdue', $overdueOpen)
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger'),
        ];
    }
}
