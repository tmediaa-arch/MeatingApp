<?php

declare(strict_types=1);

namespace App\Domains\Reports\Reports\Resolutions;

use App\Domains\Identity\Models\User;
use App\Domains\Reports\DTOs\ReportInput;
use App\Domains\Reports\DTOs\ReportResult;
use App\Domains\Reports\Reports\AbstractReport;
use App\Domains\Resolutions\Models\Resolution;
use Illuminate\Support\Facades\DB;

/**
 * گزارش نرخ اجرای مصوبات.
 */
class ResolutionsExecutionRateReport extends AbstractReport
{
    public function getDisplayName(): string
    {
        return 'نرخ اجرای مصوبات';
    }

    public function getDescription(): string
    {
        return 'نرخ اجرا و وضعیت مصوبات در بازه زمانی، شامل تفکیک بر اساس وضعیت.';
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

        $query = Resolution::query()
            ->whereBetween('created_at', [$from, $to])
            ->when($input->organizationId, fn ($q, $id) => $q->where('organization_id', $id));

        $byStatus = (clone $query)
            ->select('status', DB::raw('count(*) as cnt'))
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        $total = array_sum($byStatus);
        $executed = ($byStatus['executed'] ?? 0) + ($byStatus['completed'] ?? 0);

        $rows = (clone $query)
            ->select('id', 'resolution_number', 'title', 'status', 'priority', 'due_date', 'approved_at')
            ->orderByDesc('id')
            ->limit(1000)
            ->get()
            ->toArray();

        return new ReportResult(
            rows: $rows,
            columns: [
                $this->column('resolution_number', 'شماره مصوبه'),
                $this->column('title', 'عنوان'),
                $this->column('status', 'وضعیت'),
                $this->column('priority', 'اولویت'),
                $this->column('due_date', 'مهلت', 'date'),
                $this->column('approved_at', 'تاریخ تصویب', 'datetime'),
            ],
            summary: [
                'total' => $total,
                'executed' => $executed,
                'execution_rate' => $total > 0 ? round($executed / $total * 100, 2) : 0,
                'by_status' => $byStatus,
            ],
            charts: [
                [
                    'key' => 'by_status',
                    'title' => 'تفکیک وضعیت مصوبات',
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
