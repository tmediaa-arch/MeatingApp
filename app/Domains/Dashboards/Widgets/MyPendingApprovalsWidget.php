<?php

declare(strict_types=1);

namespace App\Domains\Dashboards\Widgets;

use App\Domains\Dashboards\DTOs\WidgetData;
use App\Domains\Dashboards\Models\DashboardWidget;
use App\Domains\Identity\Models\User;
use App\Domains\Workflow\Models\UserTask;

/**
 * تعداد کارهای انتظار تأیید کاربر در گردش کار.
 */
class MyPendingApprovalsWidget extends AbstractDashboardWidget
{
    public function getData(DashboardWidget $widget, User $user, array $filters = []): WidgetData
    {
        $count = UserTask::query()
            ->whereIn('status', ['ready', 'reserved'])
            ->where(function ($q) use ($user) {
                $q->where('assignee_user_id', $user->id)
                  ->orWhereJsonContains('candidate_users', $user->id);
            })
            ->count();

        return WidgetData::stat(
            label: 'کارهای در انتظار تأیید',
            value: $count,
            unit: 'مورد',
            color: $count > 0 ? 'warning' : 'gray',
        );
    }

    public function getCacheTtlSeconds(): int
    {
        return 60; // realtime تر باشد
    }
}
