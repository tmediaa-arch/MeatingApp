<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Domains\Calendar\Services\JalaliCalendarService;
use App\Domains\Meetings\Models\Meeting;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class CalendarPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendar;
    protected static ?string $navigationLabel = 'تقویم جلسات';
    protected static ?string $title = 'تقویم جلسات';
    protected static string|\UnitEnum|null $navigationGroup = 'مدیریت جلسات';
    protected static ?int $navigationSort = 2;
    protected string $view = 'filament.admin.pages.calendar';

    public ?int $currentJalaliYear = null;
    public ?int $currentJalaliMonth = null;

    public function mount(): void
    {
        $jalali = \Morilog\Jalali\Jalalian::fromCarbon(now());
        $this->currentJalaliYear = $jalali->getYear();
        $this->currentJalaliMonth = $jalali->getMonth();
    }

    public function getEvents(string $startIso, string $endIso): array
    {
        $start = CarbonImmutable::parse($startIso);
        $end = CarbonImmutable::parse($endIso);

        $user = auth()->user();
        $service = app(JalaliCalendarService::class);

        $query = Meeting::query()
            ->with(['room', 'chairperson'])
            ->between($start, $end);

        if (! $user->hasRole('super-admin') && ! $user->hasPermissionTo('meeting.view_all')) {
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
            'draft' => '#9ca3af',
            'scheduled' => '#3b82f6',
            'invitations_sent' => '#06b6d4',
            'in_progress' => '#10b981',
            'paused' => '#f59e0b',
            'completed' => '#6b7280',
            'cancelled' => '#ef4444',
            'postponed' => '#f97316',
            default => '#6366f1',
        };
    }

    public function rescheduleMeeting(int $meetingId, string $newStartIso, string $newEndIso): array
    {
        try {
            $meeting = Meeting::findOrFail($meetingId);

            if (! auth()->user()->can('update', $meeting)) {
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
