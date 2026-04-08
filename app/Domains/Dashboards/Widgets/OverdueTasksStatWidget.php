<?php

declare(strict_types=1);

namespace App\Domains\Dashboards\Widgets;

use App\Domains\Dashboards\DTOs\WidgetData;
use App\Domains\Dashboards\Models\DashboardWidget;
use App\Domains\Identity\Models\User;
use App\Domains\Tasks\Models\Task;

/**
 * تعداد وظایف معوقه (assigned به کاربر یا کل سازمان بسته به config).
 */
class OverdueTasksStatWidget extends AbstractDashboardWidget
{
    public function getData(DashboardWidget $widget, User $user, array $filters = []): WidgetData
    {
        $config = $widget->config ?? [];
        $scope = $config['scope'] ?? 'mine'; // mine, org, all

        $query = Task::query()
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereNotNull('due_date')
            ->where('due_date', '<', now());

        if ($scope === 'mine') {
            $query->where('assignee_user_id', $user->id);
        } elseif ($scope === 'org' && $user->organization_id) {
            $query->where('organization_id', $user->organization_id);
        }

        $count = $query->count();

        return WidgetData::stat(
            label: $scope === 'mine' ? 'وظایف معوقه من' : 'وظایف معوقه سازمان',
            value: $count,
            unit: 'وظیفه',
            color: $count > 0 ? 'danger' : 'success',
        );
    }

    public function getCacheTtlSeconds(): int
    {
        return 300;
    }
}
