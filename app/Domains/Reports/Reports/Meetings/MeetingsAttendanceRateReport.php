<?php

declare(strict_types=1);

namespace App\Domains\Reports\Reports\Meetings;

use App\Domains\Identity\Models\User;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Models\MeetingParticipant;
use App\Domains\Reports\DTOs\ReportInput;
use App\Domains\Reports\DTOs\ReportResult;
use App\Domains\Reports\Reports\AbstractReport;
use Illuminate\Support\Facades\DB;

/**
 * نرخ حضور در جلسات
 *
 * برای هر کاربر/کارمند: تعداد دعوت‌ها، تعداد حضور، تعداد غیبت، نرخ حضور
 */
class MeetingsAttendanceRateReport extends AbstractReport
{
    public function getDisplayName(): string
    {
        return 'نرخ حضور در جلسات';
    }

    public function getDescription(): string
    {
        return 'نرخ حضور هر شرکت‌کننده در جلسات در بازه زمانی مشخص.';
    }

    public function getInputSchema(): array
    {
        return [
            'date_from' => ['type' => 'date', 'label' => 'از تاریخ', 'required' => true],
            'date_to' => ['type' => 'date', 'label' => 'تا تاریخ', 'required' => true],
            'min_meetings' => [
                'type' => 'number',
                'label' => 'حداقل تعداد جلسات',
                'default' => 3,
            ],
        ];
    }

    public function run(ReportInput $input, ?User $user = null): ReportResult
    {
        [$from, $to] = $this->defaultDateRange($input);
        $minMeetings = (int) $input->get('min_meetings', 3);

        $rows = MeetingParticipant::query()
            ->join('meetings', 'meetings.id', '=', 'meeting_participants.meeting_id')
            ->whereBetween('meetings.scheduled_start_at', [$from, $to])
            ->where('meetings.status', 'completed')
            ->whereNotNull('meeting_participants.user_id')
            ->select(
                'meeting_participants.user_id',
                DB::raw('COUNT(*) as total_invitations'),
                DB::raw("SUM(CASE WHEN meeting_participants.attendance_status = 'present' THEN 1 ELSE 0 END) as attended"),
                DB::raw("SUM(CASE WHEN meeting_participants.attendance_status = 'absent' THEN 1 ELSE 0 END) as absent"),
                DB::raw("SUM(CASE WHEN meeting_participants.attendance_status = 'excused' THEN 1 ELSE 0 END) as excused"),
            )
            ->groupBy('meeting_participants.user_id')
            ->having('total_invitations', '>=', $minMeetings)
            ->get();

        // attach name و sort
        $userIds = $rows->pluck('user_id')->all();
        $users = User::whereIn('id', $userIds)->pluck('name', 'id')->toArray();

        $enriched = $rows->map(function ($r) use ($users) {
            $total = (int) $r->total_invitations;
            $attended = (int) $r->attended;
            return [
                'user_id' => $r->user_id,
                'name' => $users[$r->user_id] ?? '—',
                'total_invitations' => $total,
                'attended' => $attended,
                'absent' => (int) $r->absent,
                'excused' => (int) $r->excused,
                'attendance_rate' => $total > 0 ? round($attended / $total * 100, 2) : 0.0,
            ];
        })
        ->sortByDesc('attendance_rate')
        ->values()
        ->toArray();

        $avgAttendance = count($enriched) > 0
            ? round(array_sum(array_column($enriched, 'attendance_rate')) / count($enriched), 2)
            : 0;

        return new ReportResult(
            rows: $enriched,
            columns: [
                $this->column('name', 'نام'),
                $this->column('total_invitations', 'کل دعوت‌ها', 'number'),
                $this->column('attended', 'حاضر', 'number'),
                $this->column('absent', 'غایب', 'number'),
                $this->column('excused', 'مرخصی', 'number'),
                $this->column('attendance_rate', 'نرخ حضور', 'percentage'),
            ],
            summary: [
                'participants_count' => count($enriched),
                'avg_attendance_rate' => $avgAttendance,
            ],
            charts: [
                [
                    'key' => 'top_attendees',
                    'title' => 'بالاترین نرخ حضور (Top 10)',
                    'type' => 'bar',
                    'data' => array_slice($enriched, 0, 10),
                ],
            ],
            meta: ['date_from' => $from->format('Y-m-d'), 'date_to' => $to->format('Y-m-d')],
        );
    }
}
