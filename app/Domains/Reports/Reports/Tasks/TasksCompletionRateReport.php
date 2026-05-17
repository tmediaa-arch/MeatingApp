<?php

declare(strict_types=1);

namespace App\Domains\Reports\Reports\Tasks;

use App\Domains\Identity\Models\User;
use App\Domains\Reports\DTOs\ReportInput;
use App\Domains\Reports\DTOs\ReportResult;
use App\Domains\Reports\Reports\AbstractReport;
use App\Domains\Tasks\Models\Task;
use Illuminate\Support\Facades\DB;

class TasksCompletionRateReport extends AbstractReport
{
    public function getDisplayName(): string
    {
        return 'نرخ تکمیل وظایف';
    }

    public function getDescription(): string
    {
        return 'درصد وظایف تکمیل شده در بازه و میانگین زمان تکمیل.';
    }

    public function getInputSchema(): array
    {
        return [
            'date_from' => ['type' => 'date', 'label' => 'از تاریخ', 'required' => true],
            'date_to' => ['type' => 'date', 'label' => 'تا تاریخ', 'required' => true],
        ];
    }

    public function run(ReportInput $input, ?User $user = null): ReportResult
    {
        [$from, $to] = $this->defaultDateRange($input);

        $byStatus = Task::query()
            ->whereBetween('created_at', [$from, $to])
            ->when($input->organizationId, fn ($q, $id) => $q->where('organization_id', $id))
            ->select('status', DB::raw('count(*) as cnt'))
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        $total = array_sum($byStatus);
        $completed = $byStatus['completed'] ?? 0;

        // میانگین زمان تکمیل (روز) — عبارت بسته به درایور دیتابیس متفاوت است.
        $avgDaysExpr = DB::connection()->getDriverName() === 'pgsql'
            ? 'AVG(EXTRACT(EPOCH FROM (completed_at - created_at))/86400)'
            : 'AVG(TIMESTAMPDIFF(SECOND, created_at, completed_at)/86400)';

        $avgDays = Task::query()
            ->whereBetween('created_at', [$from, $to])
            ->whereNotNull('completed_at')
            ->select(DB::raw("{$avgDaysExpr} as avg_days"))
            ->value('avg_days');

        return new ReportResult(
            rows: [],
            columns: [],
            summary: [
                'total' => $total,
                'completed' => $completed,
                'completion_rate' => $total > 0 ? round($completed / $total * 100, 2) : 0,
                'avg_completion_days' => round((float) $avgDays, 1),
                'by_status' => $byStatus,
            ],
            charts: [
                ['key' => 'by_status', 'title' => 'تفکیک وضعیت', 'type' => 'pie', 'data' => $byStatus],
            ],
        );
    }
}
