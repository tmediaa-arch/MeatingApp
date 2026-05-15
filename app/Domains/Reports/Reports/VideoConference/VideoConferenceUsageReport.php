<?php

declare(strict_types=1);

namespace App\Domains\Reports\Reports\VideoConference;

use App\Domains\Identity\Models\User;
use App\Domains\Reports\DTOs\ReportInput;
use App\Domains\Reports\DTOs\ReportResult;
use App\Domains\Reports\Reports\AbstractReport;
use App\Domains\VideoConference\Models\VideoConferenceRoom;
use Illuminate\Support\Facades\DB;

/**
 * گزارش استفاده از ویدئوکنفرانس.
 */
class VideoConferenceUsageReport extends AbstractReport
{
    public function getDisplayName(): string
    {
        return 'استفاده از ویدئوکنفرانس';
    }

    public function getDescription(): string
    {
        return 'آمار جلسات ویدئوکنفرانس برگزارشده و وضعیت آن‌ها در بازه زمانی.';
    }

    public function getInputSchema(): array
    {
        return [
            'date_from' => ['type' => 'date', 'label' => 'از تاریخ', 'required' => false],
            'date_to' => ['type' => 'date', 'label' => 'تا تاریخ', 'required' => false],
        ];
    }

    public function run(ReportInput $input, ?User $user = null): ReportResult
    {
        [$from, $to] = $this->defaultDateRange($input);

        $query = VideoConferenceRoom::query()
            ->whereBetween('scheduled_start_at', [$from, $to]);

        $byStatus = (clone $query)
            ->select('status', DB::raw('count(*) as cnt'))
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        $byDriver = (clone $query)
            ->select('driver', DB::raw('count(*) as cnt'))
            ->groupBy('driver')
            ->pluck('cnt', 'driver')
            ->toArray();

        $total = array_sum($byStatus);

        $rows = (clone $query)
            ->select('id', 'subject', 'driver', 'status', 'scheduled_start_at', 'actual_start_at', 'actual_end_at')
            ->orderByDesc('id')
            ->limit(1000)
            ->get()
            ->toArray();

        return new ReportResult(
            rows: $rows,
            columns: [
                $this->column('subject', 'موضوع'),
                $this->column('driver', 'سرویس‌دهنده'),
                $this->column('status', 'وضعیت'),
                $this->column('scheduled_start_at', 'شروع برنامه‌ریزی‌شده', 'datetime'),
                $this->column('actual_start_at', 'شروع واقعی', 'datetime'),
                $this->column('actual_end_at', 'پایان واقعی', 'datetime'),
            ],
            summary: [
                'total' => $total,
                'by_status' => $byStatus,
                'by_driver' => $byDriver,
            ],
            charts: [
                [
                    'key' => 'by_driver',
                    'title' => 'جلسات بر اساس سرویس‌دهنده',
                    'type' => 'doughnut',
                    'data' => $byDriver,
                ],
            ],
            meta: [
                'date_from' => $from->format('Y-m-d'),
                'date_to' => $to->format('Y-m-d'),
            ],
        );
    }
}
