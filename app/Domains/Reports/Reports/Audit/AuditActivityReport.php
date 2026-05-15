<?php

declare(strict_types=1);

namespace App\Domains\Reports\Reports\Audit;

use App\Domains\Audit\Models\AuditLog;
use App\Domains\Identity\Models\User;
use App\Domains\Reports\DTOs\ReportInput;
use App\Domains\Reports\DTOs\ReportResult;
use App\Domains\Reports\Reports\AbstractReport;
use Illuminate\Support\Facades\DB;

/**
 * گزارش فعالیت ممیزی — رویدادهای ثبت‌شده در audit log.
 */
class AuditActivityReport extends AbstractReport
{
    public function getDisplayName(): string
    {
        return 'گزارش فعالیت ممیزی';
    }

    public function getDescription(): string
    {
        return 'خلاصه رویدادهای ممیزی ثبت‌شده در بازه زمانی، تفکیک بر اساس دسته و شدت.';
    }

    public function getInputSchema(): array
    {
        return [
            'date_from' => ['type' => 'date', 'label' => 'از تاریخ', 'required' => false],
            'date_to' => ['type' => 'date', 'label' => 'تا تاریخ', 'required' => false],
        ];
    }

    public function isCacheable(): bool
    {
        return false;
    }

    public function run(ReportInput $input, ?User $user = null): ReportResult
    {
        [$from, $to] = $this->defaultDateRange($input);

        $query = AuditLog::query()
            ->whereBetween('performed_at', [$from, $to]);

        $byCategory = (clone $query)
            ->select('action_category', DB::raw('count(*) as cnt'))
            ->groupBy('action_category')
            ->pluck('cnt', 'action_category')
            ->toArray();

        $bySeverity = (clone $query)
            ->select('severity', DB::raw('count(*) as cnt'))
            ->groupBy('severity')
            ->pluck('cnt', 'severity')
            ->toArray();

        $rows = (clone $query)
            ->select('id', 'event', 'action_category', 'user_display_name', 'severity', 'performed_at')
            ->orderByDesc('id')
            ->limit(1000)
            ->get()
            ->toArray();

        return new ReportResult(
            rows: $rows,
            columns: [
                $this->column('event', 'رویداد'),
                $this->column('action_category', 'دسته'),
                $this->column('user_display_name', 'کاربر'),
                $this->column('severity', 'شدت'),
                $this->column('performed_at', 'زمان', 'datetime'),
            ],
            summary: [
                'total' => array_sum($byCategory),
                'by_category' => $byCategory,
                'by_severity' => $bySeverity,
            ],
            charts: [
                [
                    'key' => 'by_category',
                    'title' => 'رویدادها بر اساس دسته',
                    'type' => 'bar',
                    'data' => $byCategory,
                ],
            ],
            meta: [
                'date_from' => $from->format('Y-m-d'),
                'date_to' => $to->format('Y-m-d'),
            ],
        );
    }
}
