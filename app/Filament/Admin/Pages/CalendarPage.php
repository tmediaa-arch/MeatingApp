<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Domains\Calendar\Services\JalaliCalendarService;
use App\Domains\Meetings\Models\Meeting;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;

class CalendarPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calendar';
    protected static ?string $navigationLabel = 'تقویم جلسات';
    protected static ?string $title = 'تقویم جلسات';
    protected static ?string $navigationGroup = 'مدیریت جلسات';
    protected static ?int $navigationSort = 2;
    protected static string $view = 'filament.admin.pages.calendar';

    public ?int $currentJalaliYear = null;
    public ?int $currentJalaliMonth = null;

    public function mount(): void
    {
        $jalali = \Morilog\Jalali\Jalalian::fromCarbon(now());
        $this->currentJalaliYear = $jalali->getYear();
        $this->currentJalaliMonth = $jalali->getMonth();
    }

    /**
     * این متد از فرانت‌اند (FullCalendar Shamsi) با تاریخ‌های Gregorian فراخوانی می‌شود
     * و رویدادهای آن بازه را برمی‌گرداند.
     *
     * @return array<array{id, title, start, end, color, ...}>
     */
    public function getEvents(string $startIso, string $endIso): array
    {
        $start = CarbonImmutable::parse($startIso);
        $end = CarbonImmutable::parse($endIso);

        $user = auth()->user();
        $service = app(JalaliCalendarService::class);

        $query = Meeting::query()
            ->with(['room', 'chairperson'])
            ->between($start, $end);

        // دسترسی: کاربر فقط جلسات قابل مشاهده برای خودش را می‌بیند
        if (!$user->hasRole('super-admin') && !$user->hasPermissionTo('meeting.view_all')) {
            $query->forUser($user);
        }

        return $query->get()->map(function (Meeting $meeting) use ($service) {
            return [
                'id' => $meeting->id,
                'title' => $meeting->meeting_number . ' — ' . $meeting->subject,
                'start' => $meeting->scheduled_start_at->toIso8601String(),
                'end' => $meeting->scheduled_end_at->toIso8601String(),
                'color' => $this->statusColor($meeting->status->value),
                'borderColor' => $this->statusColor($meeting->status->value),
                'extendedProps' => [
                    'status' => $meeting->status->label(),
                    'mode' => $meeting->mode->label(),
                    'room' => $meeting->room?->name,
                    'chairperson' => $meeting->chairperson?->full_name,
                    'jalali_start' => $service->formatHuman($meeting->scheduled_start_at),
                    'url' => route('filament.admin.resources.meetings.view', $meeting),
                ],
            ];
        })->toArray();
    }

    private function statusColor(string $status): string
    {
        return match ($status) {
            'draft' => '#9ca3af',           // gray
            'scheduled' => '#3b82f6',       // blue
            'invitations_sent' => '#06b6d4', // cyan
            'in_progress' => '#10b981',     // green
            'paused' => '#f59e0b',          // amber
            'completed' => '#6b7280',       // gray-dark
            'cancelled' => '#ef4444',       // red
            'postponed' => '#f97316',       // orange
            default => '#6366f1',           // indigo
        };
    }

    /**
     * Listener برای drag/drop تغییر زمان جلسه از روی تقویم
     */
    public function rescheduleMeeting(int $meetingId, string $newStartIso, string $newEndIso): array
    {
        try {
            $meeting = Meeting::findOrFail($meetingId);

            // policy check
            if (!auth()->user()->can('update', $meeting)) {
                return ['success' => false, 'message' => 'دسترسی غیرمجاز'];
            }

            app(\App\Domains\Meetings\Actions\RescheduleMeetingAction::class)
                ->execute(
                    meeting: $meeting,
                    newStart: CarbonImmutable::parse($newStartIso),
                    newEnd: CarbonImmutable::parse($newEndIso),
                    reason: 'تغییر از روی تقویم',
                );

            return ['success' => true, 'message' => 'زمان جلسه به‌روزرسانی شد'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getViewData(): array
    {
        return [
            'currentJalaliYear' => $this->currentJalaliYear,
            'currentJalaliMonth' => $this->currentJalaliMonth,
        ];
    }
}
