<?php

declare(strict_types=1);

namespace App\Domains\Reports\Reports\Workflow;

use App\Domains\Identity\Models\User;
use App\Domains\Reports\DTOs\ReportInput;
use App\Domains\Reports\DTOs\ReportResult;
use App\Domains\Reports\Reports\AbstractReport;
use App\Domains\Workflow\Models\ProcessInstance;
use Illuminate\Support\Facades\DB;

/**
 * گزارش instance های گردش کار.
 */
class WorkflowInstancesReport extends AbstractReport
{
    public function getDisplayName(): string
    {
        return 'instance های گردش کار';
    }

    public function getDescription(): string
    {
        return 'وضعیت instance های فرایندهای گردش کار در بازه زمانی انتخابی.';
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

        $query = ProcessInstance::query()
            ->whereBetween('started_at', [$from, $to])
            ->when($input->organizationId, fn ($q, $id) => $q->where('organization_id', $id));

        $byStatus = (clone $query)
            ->select('status', DB::raw('count(*) as cnt'))
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        $byProcess = (clone $query)
            ->select('process_key', DB::raw('count(*) as cnt'))
            ->groupBy('process_key')
            ->pluck('cnt', 'process_key')
            ->toArray();

        $total = array_sum($byStatus);
        $completed = $byStatus['completed'] ?? 0;

        $rows = (clone $query)
            ->select('id', 'process_key', 'business_key', 'subject', 'status', 'started_at', 'completed_at')
            ->orderByDesc('id')
            ->limit(1000)
            ->get()
            ->toArray();

        return new ReportResult(
            rows: $rows,
            columns: [
                $this->column('process_key', 'کلید فرایند'),
                $this->column('business_key', 'کلید کسب‌وکار'),
                $this->column('subject', 'موضوع'),
                $this->column('status', 'وضعیت'),
                $this->column('started_at', 'شروع', 'datetime'),
                $this->column('completed_at', 'پایان', 'datetime'),
            ],
            summary: [
                'total' => $total,
                'completed' => $completed,
                'completion_rate' => $total > 0 ? round($completed / $total * 100, 2) : 0,
                'by_status' => $byStatus,
                'by_process' => $byProcess,
            ],
            charts: [
                [
                    'key' => 'by_status',
                    'title' => 'instance ها بر اساس وضعیت',
                    'type' => 'doughnut',
                    'data' => $byStatus,
                ],
                [
                    'key' => 'by_process',
                    'title' => 'instance ها بر اساس فرایند',
                    'type' => 'bar',
                    'data' => $byProcess,
                ],
            ],
            meta: [
                'date_from' => $from->format('Y-m-d'),
                'date_to' => $to->format('Y-m-d'),
            ],
        );
    }
}
