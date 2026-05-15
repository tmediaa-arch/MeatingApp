<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domains\Notifications\Models\NotificationOutbox;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class UnreadInboxWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $user = auth()->user();

        $unread = NotificationOutbox::query()
            ->forInbox($user)
            ->unread()
            ->count();

        $today = NotificationOutbox::query()
            ->where('recipient_user_id', $user->id)
            ->whereDate('created_at', today())
            ->count();

        $thisWeek = NotificationOutbox::query()
            ->where('recipient_user_id', $user->id)
            ->where('created_at', '>=', now()->startOfWeek())
            ->count();

        return [
            Stat::make('اعلان‌های خوانده‌نشده', $unread)
                ->description($unread > 0 ? 'منتظر مشاهده' : 'همه را خوانده‌اید')
                ->descriptionIcon(Heroicon::MiniInbox)
                ->color($unread > 5 ? 'danger' : ($unread > 0 ? 'warning' : 'success')),

            Stat::make('اعلان امروز', $today)
                ->description('در ۲۴ ساعت گذشته')
                ->color('info'),

            Stat::make('اعلان این هفته', $thisWeek)
                ->description('از شنبه تا کنون')
                ->color('primary'),
        ];
    }
}
