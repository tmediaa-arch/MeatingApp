<?php

declare(strict_types=1);

namespace App\Domains\Dashboards\Widgets;

use App\Domains\Dashboards\DTOs\WidgetData;
use App\Domains\Dashboards\Models\DashboardWidget;
use App\Domains\Identity\Models\User;
use App\Domains\Tasks\Models\Task;

class TasksCompletionGaugeWidget extends AbstractDashboardWidget
{
    public function getData(DashboardWidget $widget, User $user, array $filters = []): WidgetData
    {
        $days = (int) ($widget->config['days'] ?? 30);
        $from = now()->subDays($days);

        $query = Task::query()
            ->where('created_at', '>=', $from)
            ->when($user->organization_id, fn ($q, $id) => $q->where('organization_id', $id));

        $total = (clone $query)->count();
        $completed = (clone $query)->where('status', 'completed')->count();
        $rate = $total > 0 ? round($completed / $total * 100, 1) : 0;

        return WidgetData::stat(
            label: "نرخ تکمیل وظایف ({$days} روز)",
            value: $rate,
            unit: '%',
            color: $rate >= 70 ? 'success' : ($rate >= 40 ? 'warning' : 'danger'),
        );
    }
}
