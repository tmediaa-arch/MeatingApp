<?php

declare(strict_types=1);

namespace App\Domains\Dashboards\Widgets;

use App\Domains\Dashboards\DTOs\WidgetData;
use App\Domains\Dashboards\Models\DashboardWidget;
use App\Domains\Identity\Models\User;
use App\Domains\Minutes\Models\Minute;

class RecentMinutesListWidget extends AbstractDashboardWidget
{
    public function getData(DashboardWidget $widget, User $user, array $filters = []): WidgetData
    {
        $limit = (int) ($widget->config['limit'] ?? 10);

        $minutes = Minute::query()
            ->when($user->organization_id, fn ($q, $id) => $q->where('organization_id', $id))
            ->where('status', 'published')
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get(['id', 'minute_number', 'title', 'published_at'])
            ->map(fn ($m) => [
                'id' => $m->id,
                'title' => $m->minute_number . ' — ' . $m->title,
                'subtitle' => $m->published_at?->diffForHumans(),
                'link' => "/admin/minutes/{$m->id}",
            ])
            ->toArray();

        return WidgetData::list($minutes, 'آخرین صورتجلسات');
    }
}
