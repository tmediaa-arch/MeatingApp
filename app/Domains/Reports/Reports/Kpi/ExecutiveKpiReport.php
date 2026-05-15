<?php

declare(strict_types=1);

namespace App\Domains\Reports\Reports\Kpi;

use App\Domains\Identity\Models\User;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Reports\DTOs\ReportInput;
use App\Domains\Reports\DTOs\ReportResult;
use App\Domains\Reports\Reports\AbstractReport;
use App\Domains\Resolutions\Models\Resolution;
use App\Domains\Tasks\Models\Task;

/**
 * گزارش شاخص‌های کلیدی مدیریتی (KPI).
 *
 * جمع‌بندی سطح‌بالا از جلسات، مصوبات و وظایف برای مدیران ارشد.
 */
class ExecutiveKpiReport extends AbstractReport
{
    public function getDisplayName(): string
    {
        return 'شاخص‌های کلیدی مدیریتی';
    }

    public function getDescription(): string
    {
        return 'خلاصه شاخص‌های کلیدی سازمان: جلسات، مصوبات و وظایف در بازه زمانی.';
    }

    public function getInputSchema(): array
    {
        return [
            'date_from' => ['type' => 'date', 'label' => 'از تاریخ', 'required' => false],
            'date_to' => ['type' => 'date', 'label' => 'تا تاریخ', 'required' => false],
            'organization_id' => ['type' => 'organization', 'label' => 'سازمان', 'required' => false],
        ];
    }

    public function getCacheTtlMinutes(): int
    {
        return 30;
    }

    public function run(ReportInput $input, ?User $user = null): ReportResult
    {
        [$from, $to] = $this->defaultDateRange($input);
        $orgId = $input->organizationId;

        $meetings = Meeting::query()
            ->whereBetween('scheduled_start_at', [$from, $to])
            ->when($orgId, fn ($q, $id) => $q->where('organization_id', $id));

        $resolutions = Resolution::query()
            ->whereBetween('created_at', [$from, $to])
            ->when($orgId, fn ($q, $id) => $q->where('organization_id', $id));

        $tasks = Task::query()
            ->whereBetween('created_at', [$from, $to])
            ->when($orgId, fn ($q, $id) => $q->where('organization_id', $id));

        $meetingsTotal = (clone $meetings)->count();
        $meetingsCompleted = (clone $meetings)->where('status', 'completed')->count();

        $resolutionsTotal = (clone $resolutions)->count();
        $resolutionsApproved = (clone $resolutions)->where('status', 'approved')->count();

        $tasksTotal = (clone $tasks)->count();
        $tasksCompleted = (clone $tasks)->where('status', 'completed')->count();
        $tasksOverdue = (clone $tasks)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->count();

        $rate = fn (int $part, int $whole): float => $whole > 0 ? round($part / $whole * 100, 2) : 0.0;

        $rows = [
            ['kpi' => 'جلسات برگزارشده', 'value' => $meetingsTotal, 'detail' => $meetingsCompleted . ' تکمیل‌شده'],
            ['kpi' => 'نرخ تکمیل جلسات', 'value' => $rate($meetingsCompleted, $meetingsTotal) . '%', 'detail' => ''],
            ['kpi' => 'مصوبات', 'value' => $resolutionsTotal, 'detail' => $resolutionsApproved . ' تصویب‌شده'],
            ['kpi' => 'نرخ تصویب مصوبات', 'value' => $rate($resolutionsApproved, $resolutionsTotal) . '%', 'detail' => ''],
            ['kpi' => 'وظایف', 'value' => $tasksTotal, 'detail' => $tasksCompleted . ' تکمیل‌شده'],
            ['kpi' => 'نرخ تکمیل وظایف', 'value' => $rate($tasksCompleted, $tasksTotal) . '%', 'detail' => ''],
            ['kpi' => 'وظایف معوقه', 'value' => $tasksOverdue, 'detail' => ''],
        ];

        return new ReportResult(
            rows: $rows,
            columns: [
                $this->column('kpi', 'شاخص'),
                $this->column('value', 'مقدار'),
                $this->column('detail', 'جزئیات'),
            ],
            summary: [
                'meetings_total' => $meetingsTotal,
                'meetings_completion_rate' => $rate($meetingsCompleted, $meetingsTotal),
                'resolutions_total' => $resolutionsTotal,
                'resolutions_approval_rate' => $rate($resolutionsApproved, $resolutionsTotal),
                'tasks_total' => $tasksTotal,
                'tasks_completion_rate' => $rate($tasksCompleted, $tasksTotal),
                'tasks_overdue' => $tasksOverdue,
            ],
            charts: [
                [
                    'key' => 'completion_rates',
                    'title' => 'نرخ‌های تکمیل',
                    'type' => 'bar',
                    'data' => [
                        'جلسات' => $rate($meetingsCompleted, $meetingsTotal),
                        'مصوبات' => $rate($resolutionsApproved, $resolutionsTotal),
                        'وظایف' => $rate($tasksCompleted, $tasksTotal),
                    ],
                ],
            ],
            meta: [
                'date_from' => $from->format('Y-m-d'),
                'date_to' => $to->format('Y-m-d'),
            ],
        );
    }
}
