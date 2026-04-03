<?php

declare(strict_types=1);

namespace App\Domains\Reports\Reports\Meetings;

use App\Domains\Identity\Models\User;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Reports\DTOs\ReportInput;
use App\Domains\Reports\DTOs\ReportResult;
use App\Domains\Reports\Reports\AbstractReport;
use Illuminate\Support\Facades\DB;

/**
 * گزارش خلاصه جلسات
 *
 * شامل: تعداد کل، تفکیک بر اساس وضعیت، روند روزانه، نرخ لغو.
 */
class MeetingsSummaryReport extends AbstractReport
{
    public function getDisplayName(): string
    {
        return 'خلاصه جلسات';
    }

    public function getDescription(): string
    {
        return 'گزارش خلاصه جلسات شامل تعداد، وضعیت‌ها، روند، و نرخ لغو در بازه زمانی.';
    }

    public function getInputSchema(): array
    {
        return [
            'date_from' => ['type' => 'date', 'label' => 'از تاریخ', 'required' => true],
            'date_to' => ['type' => 'date', 'label' => 'تا تاریخ', 'required' => true],
            'organization_id' => ['type' => 'organization', 'label' => 'سازمان', 'required' => false],
            'org_unit_id' => ['type' => 'org_unit', 'label' => 'واحد سازمانی', 'required' => false],
        ];
    }

    public function run(ReportInput $input, ?User $user = null): ReportResult
    {
        [$from, $to] = $this->defaultDateRange($input);

        $query = Meeting::query()
            ->whereBetween('scheduled_start_at', [$from, $to])
            ->when($input->organizationId, fn ($q, $id) => $q->where('organization_id', $id))
            ->when($input->get('org_unit_id'), fn ($q, $id) => $q->where('host_org_unit_id', $id));

        // تفکیک بر اساس وضعیت
        $byStatus = (clone $query)
            ->select('status', DB::raw('count(*) as cnt'))
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        $total = array_sum($byStatus);
        $cancelled = $byStatus['cancelled'] ?? 0;
        $completed = $byStatus['completed'] ?? 0;

        // روند روزانه
        $daily = (clone $query)
            ->select(DB::raw('DATE(scheduled_start_at) as day'), DB::raw('count(*) as cnt'))
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(fn ($r) => ['day' => $r->day, 'count' => (int) $r->cnt])
            ->toArray();

        return new ReportResult(
            rows: (clone $query)
                ->select('id', 'meeting_number', 'subject', 'status', 'scheduled_start_at', 'scheduled_end_at')
                ->orderBy('scheduled_start_at')
                ->limit(1000)
                ->get()
                ->toArray(),
            columns: [
                $this->column('meeting_number', 'شماره جلسه'),
                $this->column('subject', 'موضوع'),
                $this->column('status', 'وضعیت'),
                $this->column('scheduled_start_at', 'شروع', 'datetime'),
                $this->column('scheduled_end_at', 'پایان', 'datetime'),
            ],
            summary: [
                'total' => $total,
                'completed' => $completed,
                'cancelled' => $cancelled,
                'cancellation_rate' => $total > 0 ? round($cancelled / $total * 100, 2) : 0,
                'completion_rate' => $total > 0 ? round($completed / $total * 100, 2) : 0,
                'by_status' => $byStatus,
            ],
            charts: [
                [
                    'key' => 'by_status',
                    'title' => 'تفکیک وضعیت‌ها',
                    'type' => 'doughnut',
                    'data' => $byStatus,
                ],
                [
                    'key' => 'daily_trend',
                    'title' => 'روند روزانه',
                    'type' => 'line',
                    'data' => $daily,
                ],
            ],
            meta: [
                'date_from' => $from->format('Y-m-d'),
                'date_to' => $to->format('Y-m-d'),
            ],
        );
    }
}
