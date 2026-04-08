<?php

declare(strict_types=1);

namespace App\Domains\Dashboards\Widgets;

use App\Domains\Dashboards\DTOs\WidgetData;
use App\Domains\Dashboards\Models\DashboardWidget;
use App\Domains\Identity\Models\User;
use App\Domains\Rooms\Models\RoomReservation;
use Illuminate\Support\Facades\DB;

/**
 * استفاده از سالن‌ها — تعداد رزرو در ۳۰ روز اخیر بر اساس سالن.
 */
class RoomUtilizationWidget extends AbstractDashboardWidget
{
    public function getData(DashboardWidget $widget, User $user, array $filters = []): WidgetData
    {
        $days = (int) ($widget->config['days'] ?? 30);

        $data = RoomReservation::query()
            ->where('start_at', '>=', now()->subDays($days))
            ->whereIn('status', ['confirmed', 'completed'])
            ->join('rooms', 'rooms.id', '=', 'room_reservations.room_id')
            ->select('rooms.name', DB::raw('count(*) as cnt'))
            ->groupBy('rooms.name')
            ->orderByDesc('cnt')
            ->limit(10)
            ->pluck('cnt', 'name')
            ->toArray();

        return WidgetData::chart('bar', $data, "استفاده از سالن‌ها ({$days} روز)");
    }
}
