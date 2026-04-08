<?php

declare(strict_types=1);

namespace App\Domains\Dashboards\Widgets;

use App\Domains\Dashboards\DTOs\WidgetData;
use App\Domains\Dashboards\Models\DashboardWidget;
use App\Domains\Identity\Models\User;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Models\MeetingParticipant;

/**
 * ویجت تعداد جلسات کاربر در یک بازه (پیش‌فرض: هفته جاری).
 */
class MyMeetingsCountWidget extends AbstractDashboardWidget
{
    public function getData(DashboardWidget $widget, User $user, array $filters = []): WidgetData
    {
        $from = $filters['date_from'] ?? now()->startOfWeek();
        $to = $filters['date_to'] ?? now()->endOfWeek();

        // جلسات به عنوان شرکت‌کننده
        $participantMeetingIds = MeetingParticipant::query()
            ->where('user_id', $user->id)
            ->pluck('meeting_id');

        $count = Meeting::query()
            ->where(function ($q) use ($user, $participantMeetingIds) {
                $q->whereIn('id', $participantMeetingIds)
                  ->orWhere('host_user_id', $user->id);
            })
            ->whereBetween('scheduled_start_at', [$from, $to])
            ->whereNotIn('status', ['cancelled'])
            ->count();

        return WidgetData::stat(
            label: 'جلسات این هفته',
            value: $count,
            unit: 'جلسه',
            color: 'primary',
        );
    }

    public function getCacheTtlSeconds(): int
    {
        return 180;
    }
}
