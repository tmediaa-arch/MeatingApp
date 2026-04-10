<?php

declare(strict_types=1);

namespace App\Domains\Exports\Generators;

use App\Domains\Exports\Contracts\ExportGeneratorInterface;
use App\Domains\Exports\Enums\ExportType;
use App\Domains\Exports\Models\ExportJob;
use App\Domains\Identity\Models\User;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Models\MeetingParticipant;
use Illuminate\Support\Str;

/**
 * CalendarIcsGenerator — تولید iCalendar (.ics) با rfc 5545.
 *
 * این generator دو حالت دارد:
 * 1. Export یک‌باره: همه جلسات کاربر در یک بازه ⇒ .ics
 * 2. Feed مداوم: استفاده در CalendarFeedController که بدون نیاز به Auth
 *    یک URL پایدار برای subscribe در outlook/google calendar فراهم می‌کند.
 */
class CalendarIcsGenerator implements ExportGeneratorInterface
{
    public function supports(ExportJob $job): bool
    {
        return $job->export_type === ExportType::CalendarIcs && $job->format === 'ics';
    }

    public function generate(ExportJob $job): array
    {
        $params = $job->input_params ?? [];
        $userId = $params['user_id'] ?? $job->requested_by_user_id;
        $user = User::find($userId);

        $events = $this->collectEventsForUser($user, $params);

        $ics = $this->buildIcs($events, $user?->name ?? 'MMS');

        return [
            'content' => $ics,
            'mime' => 'text/calendar; charset=utf-8',
            'extension' => 'ics',
            'filename' => 'calendar_' . now()->format('Ymd_His') . '.ics',
            'row_count' => count($events),
        ];
    }

    /**
     * Public method — استفاده توسط Calendar Feed Controller (نه از ExportJob)
     */
    public function generateForUser(User $user, array $filterConfig = []): string
    {
        $events = $this->collectEventsForUser($user, $filterConfig);
        return $this->buildIcs($events, $user->name);
    }

    private function collectEventsForUser(?User $user, array $params = []): array
    {
        if (!$user) return [];

        // جلسات: یا میزبان است یا شرکت‌کننده
        $participantMeetingIds = MeetingParticipant::query()
            ->where('user_id', $user->id)
            ->pluck('meeting_id');

        $from = $params['date_from'] ?? now()->subMonth()->toDateString();
        $to = $params['date_to'] ?? now()->addMonths(6)->toDateString();

        $meetings = Meeting::query()
            ->where(function ($q) use ($user, $participantMeetingIds) {
                $q->where('host_user_id', $user->id)
                  ->orWhereIn('id', $participantMeetingIds);
            })
            ->whereBetween('scheduled_start_at', [$from, $to])
            ->whereNotIn('status', ['cancelled'])
            ->get();

        return $meetings->map(fn ($m) => $this->meetingToEvent($m))->toArray();
    }

    private function meetingToEvent(Meeting $meeting): array
    {
        return [
            'uid' => "meeting-{$meeting->id}@mms",
            'summary' => $meeting->subject,
            'description' => $meeting->description ?? '',
            'location' => $meeting->room?->name ?? '',
            'start' => $meeting->scheduled_start_at,
            'end' => $meeting->scheduled_end_at,
            'status' => match ($meeting->status?->value ?? '') {
                'completed' => 'CONFIRMED',
                'cancelled' => 'CANCELLED',
                default => 'TENTATIVE',
            },
            'last_modified' => $meeting->updated_at,
        ];
    }

    private function buildIcs(array $events, string $owner): string
    {
        $now = gmdate('Ymd\THis\Z');
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//MMS//Meeting Management System//FA',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . $this->escape("جلسات — {$owner}"),
            'X-WR-TIMEZONE:Asia/Tehran',
        ];

        foreach ($events as $event) {
            if (!$event['start'] || !$event['end']) continue;

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:' . $event['uid'];
            $lines[] = 'DTSTAMP:' . $now;
            $lines[] = 'DTSTART:' . gmdate('Ymd\THis\Z', strtotime((string) $event['start']));
            $lines[] = 'DTEND:' . gmdate('Ymd\THis\Z', strtotime((string) $event['end']));
            $lines[] = 'SUMMARY:' . $this->escape((string) $event['summary']);
            if (!empty($event['description'])) {
                $lines[] = 'DESCRIPTION:' . $this->escape($this->stripHtml((string) $event['description']));
            }
            if (!empty($event['location'])) {
                $lines[] = 'LOCATION:' . $this->escape((string) $event['location']);
            }
            $lines[] = 'STATUS:' . $event['status'];
            if (!empty($event['last_modified'])) {
                $lines[] = 'LAST-MODIFIED:' . gmdate('Ymd\THis\Z', strtotime((string) $event['last_modified']));
            }
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines) . "\r\n";
    }

    private function escape(string $value): string
    {
        // RFC 5545 escaping
        $value = str_replace(['\\', "\n", ',', ';'], ['\\\\', '\\n', '\\,', '\\;'], $value);
        return $value;
    }

    private function stripHtml(string $value): string
    {
        return trim(strip_tags($value));
    }
}
