<?php

declare(strict_types=1);

namespace App\Domains\Dashboards\Widgets;

use App\Domains\Dashboards\DTOs\WidgetData;
use App\Domains\Dashboards\Models\DashboardWidget;
use App\Domains\Identity\Models\User;
use App\Domains\Resolutions\Models\Resolution;
use Illuminate\Support\Facades\DB;

class ResolutionsStatusChartWidget extends AbstractDashboardWidget
{
    public function getData(DashboardWidget $widget, User $user, array $filters = []): WidgetData
    {
        $days = (int) ($widget->config['days'] ?? 90);

        $byStatus = Resolution::query()
            ->where('created_at', '>=', now()->subDays($days))
            ->when($user->organization_id, fn ($q, $id) => $q->where('organization_id', $id))
            ->select('status', DB::raw('count(*) as cnt'))
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        return WidgetData::chart('doughnut', $byStatus, 'وضعیت مصوبات');
    }
}
