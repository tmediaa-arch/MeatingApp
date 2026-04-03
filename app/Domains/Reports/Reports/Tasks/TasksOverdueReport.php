<?php

declare(strict_types=1);

namespace App\Domains\Reports\Reports\Tasks;

use App\Domains\Identity\Models\User;
use App\Domains\Reports\DTOs\ReportInput;
use App\Domains\Reports\DTOs\ReportResult;
use App\Domains\Reports\Reports\AbstractReport;
use App\Domains\Tasks\Models\Task;
use Illuminate\Support\Facades\DB;

class TasksOverdueReport extends AbstractReport
{
    public function getDisplayName(): string
    {
        return 'وظایف معوقه';
    }

    public function getDescription(): string
    {
        return 'لیست همه وظایفی که مهلت آن‌ها گذشته اما هنوز تکمیل نشده‌اند.';
    }

    public function getInputSchema(): array
    {
        return [
            'organization_id' => ['type' => 'organization', 'label' => 'سازمان'],
            'priority' => [
                'type' => 'select',
                'label' => 'اولویت',
                'options' => ['critical', 'high', 'normal', 'low'],
            ],
        ];
    }

    public function isCacheable(): bool
    {
        return true;
    }

    public function getCacheTtlMinutes(): int
    {
        return 15; // داده‌های live تر
    }

    public function run(ReportInput $input, ?User $user = null): ReportResult
    {
        $query = Task::query()
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->when($input->organizationId, fn ($q, $id) => $q->where('organization_id', $id))
            ->when($input->get('priority'), fn ($q, $p) => $q->where('priority', $p));

        $rows = (clone $query)
            ->select(
                'id', 'task_number', 'title', 'status', 'priority',
                'due_date', 'assignee_user_id', 'escalation_level', 'progress_percent',
                DB::raw("EXTRACT(EPOCH FROM (NOW() - due_date))/86400 as days_overdue")
            )
            ->orderBy('escalation_level', 'desc')
            ->orderBy('due_date')
            ->limit(1000)
            ->get()
            ->map(function ($r) {
                return [
                    'task_number' => $r->task_number,
                    'title' => $r->title,
                    'status' => $r->status,
                    'priority' => $r->priority,
                    'due_date' => $r->due_date,
                    'days_overdue' => round((float) $r->days_overdue, 1),
                    'escalation_level' => $r->escalation_level,
                    'progress_percent' => $r->progress_percent,
                ];
            })
            ->toArray();

        // تفکیک بر اساس escalation
        $byLevel = (clone $query)
            ->select('escalation_level', DB::raw('count(*) as cnt'))
            ->groupBy('escalation_level')
            ->pluck('cnt', 'escalation_level')
            ->toArray();

        return new ReportResult(
            rows: $rows,
            columns: [
                $this->column('task_number', 'شماره وظیفه'),
                $this->column('title', 'عنوان'),
                $this->column('priority', 'اولویت'),
                $this->column('due_date', 'مهلت', 'date'),
                $this->column('days_overdue', 'روز تأخیر', 'number'),
                $this->column('escalation_level', 'سطح Escalation', 'number'),
                $this->column('progress_percent', 'پیشرفت', 'percentage'),
            ],
            summary: [
                'total_overdue' => count($rows),
                'by_escalation_level' => $byLevel,
                'critical_count' => count(array_filter($rows, fn ($r) => $r['priority'] === 'critical')),
            ],
            charts: [
                [
                    'key' => 'by_level',
                    'title' => 'وظایف معوقه بر اساس سطح Escalation',
                    'type' => 'bar',
                    'data' => $byLevel,
                ],
            ],
        );
    }
}
