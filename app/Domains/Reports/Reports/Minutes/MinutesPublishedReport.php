<?php

declare(strict_types=1);

namespace App\Domains\Reports\Reports\Minutes;

use App\Domains\Identity\Models\User;
use App\Domains\Minutes\Models\Minute;
use App\Domains\Reports\DTOs\ReportInput;
use App\Domains\Reports\DTOs\ReportResult;
use App\Domains\Reports\Reports\AbstractReport;
use Illuminate\Support\Facades\DB;

/**
 * گزارش صورتجلسات منتشر شده.
 */
class MinutesPublishedReport extends AbstractReport
{
    public function getDisplayName(): string
    {
        return 'صورتجلسات منتشر شده';
    }

    public function getDescription(): string
    {
        return 'فهرست صورتجلسات و وضعیت انتشار آن‌ها در بازه زمانی انتخابی.';
    }

    public function getInputSchema(): array
    {
        return [
            'date_from' => ['type' => 'date', 'label' => 'از تاریخ', 'required' => false],
            'date_to' => ['type' => 'date', 'label' => 'تا تاریخ', 'required' => false],
            'organization_id' => ['type' => 'organization', 'label' => 'سازمان', 'required' => false],
        ];
    }

    public function run(ReportInput $input, ?User $user = null): ReportResult
    {
        [$from, $to] = $this->defaultDateRange($input);

        $query = Minute::query()
            ->whereBetween('created_at', [$from, $to])
            ->when($input->organizationId, fn ($q, $id) => $q->where('organization_id', $id));

        $byStatus = (clone $query)
            ->select('status', DB::raw('count(*) as cnt'))
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        $total = array_sum($byStatus);
        $published = $byStatus['published'] ?? 0;

        $rows = (clone $query)
            ->select('id', 'minute_number', 'title', 'status', 'published_at')
            ->orderByDesc('id')
            ->limit(1000)
            ->get()
            ->toArray();

        return new ReportResult(
            rows: $rows,
            columns: [
                $this->column('minute_number', 'شماره صورتجلسه'),
                $this->column('title', 'عنوان'),
                $this->column('status', 'وضعیت'),
                $this->column('published_at', 'تاریخ انتشار', 'datetime'),
            ],
            summary: [
                'total' => $total,
                'published' => $published,
                'publish_rate' => $total > 0 ? round($published / $total * 100, 2) : 0,
                'by_status' => $byStatus,
            ],
            charts: [
                [
                    'key' => 'by_status',
                    'title' => 'تفکیک وضعیت صورتجلسات',
                    'type' => 'doughnut',
                    'data' => $byStatus,
                ],
            ],
            meta: [
                'date_from' => $from->format('Y-m-d'),
                'date_to' => $to->format('Y-m-d'),
            ],
        );
    }
}
