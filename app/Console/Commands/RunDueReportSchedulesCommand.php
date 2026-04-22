<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Reports\Actions\RunReportAction;
use App\Domains\Reports\Enums\ReportFormat;
use App\Domains\Reports\Models\ReportSchedule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Phase 6 — اجرای schedule های گزارش که moment فعلی به آن‌ها رسیده.
 *
 * این command هر دقیقه اجرا می‌شود.
 */
class RunDueReportSchedulesCommand extends Command
{
    protected $signature = 'reports:run-schedules';
    protected $description = 'اجرای گزارش‌های زمان‌بندی شده که موعد اجرای آن‌ها رسیده';

    public function handle(RunReportAction $action): int
    {
        $due = ReportSchedule::query()->due()->get();

        if ($due->isEmpty()) {
            $this->info('هیچ schedule due ای پیدا نشد.');
            return self::SUCCESS;
        }

        $this->info("تعداد {$due->count()} schedule due پیدا شد.");

        foreach ($due as $schedule) {
            try {
                $action->execute(
                    report: $schedule->report,
                    params: $schedule->input_params ?? [],
                    user: $schedule->creator,
                    format: ReportFormat::from($schedule->output_format),
                );

                $schedule->forceFill([
                    'last_run_at' => now(),
                ])->save();
                $schedule->updateNextRun();

                $this->info("✓ Schedule #{$schedule->id} ({$schedule->name}) اجرا شد.");
            } catch (\Throwable $e) {
                Log::error("Schedule execution failed: #{$schedule->id}", [
                    'error' => $e->getMessage(),
                ]);
                $this->error("✗ Schedule #{$schedule->id} ناموفق: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
