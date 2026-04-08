<?php

declare(strict_types=1);

namespace App\Domains\Dashboards\Widgets;

use App\Domains\Dashboards\DTOs\WidgetData;
use App\Domains\Dashboards\Models\DashboardWidget;
use App\Domains\Identity\Models\User;
use App\Domains\Meetings\Models\Meeting;
use Illuminate\Support\Facades\DB;

/**
 * روند تعداد جلسات در ۱۲ هفته اخیر.
 */
class AttendanceTrendChartWidget extends AbstractDashboardWidget
{
    public function getData(DashboardWidget $widget, User $user, array $filters = []): WidgetData
    {
        $weeks = (int) ($widget->config['weeks'] ?? 12);
        $from = now()->subWeeks($weeks)->startOfWeek();

        $data = Meeting::query()
            ->where('scheduled_start_at', '>=', $from)
            ->where('status', 'completed')
            ->when($user->organization_id, fn ($q, $id) => $q->where('organization_id', $id))
            ->select(
                DB::raw("DATE_TRUNC('week', scheduled_start_at) as week"),
                DB::raw('COUNT(*) as cnt')
            )
            ->groupBy('week')
            ->orderBy('week')
            ->get()
            ->map(fn ($r) => [
                'label' => date('Y-W', strtotime($r->week)),
                'value' => (int) $r->cnt,
            ])
            ->toArray();

        return WidgetData::chart(
            chartType: 'line',
            data: $data,
            title: "روند جلسات در {$weeks} هفته اخیر",
        );
    }
}
