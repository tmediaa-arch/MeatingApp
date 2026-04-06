<?php

declare(strict_types=1);

namespace App\Domains\Reports\Services;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Identity\Models\User;
use App\Domains\Reports\DTOs\ReportInput;
use App\Domains\Reports\DTOs\ReportResult;
use App\Domains\Reports\Enums\ReportRunStatus;
use App\Domains\Reports\Exceptions\ReportException;
use App\Domains\Reports\Models\Report;
use App\Domains\Reports\Models\ReportRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ReportRunnerService — هسته اجرای گزارش‌ها.
 *
 * مسیر اجرا:
 * 1. ReportRun ایجاد می‌شود با status=queued
 * 2. اگر گزارش cacheable است و run مشابه با cached_until معتبر موجود است،
 *    داده‌های آن استفاده می‌شود (و run جدید آن را بازنگری می‌کند).
 * 3. در غیر این صورت، handler resolve و اجرا می‌شود.
 * 4. result_data ذخیره و در صورت نیاز cached_until محاسبه می‌شود.
 *
 * این سرویس فقط داده تولید می‌کند؛ rendering به فرمت‌های مختلف
 * توسط ReportRenderingService انجام می‌شود.
 */
class ReportRunnerService
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {
    }

    public function run(
        Report $report,
        ReportInput $input,
        ?User $requestedBy = null,
        bool $forceFresh = false,
    ): ReportRun {
        if (!$report->is_active) {
            throw ReportException::reportInactive($report->key);
        }

        $paramsHash = $input->hash();

        // 1. Cache lookup
        if (!$forceFresh && $report->is_cacheable) {
            $cached = ReportRun::query()
                ->where('report_id', $report->id)
                ->fresh($paramsHash)
                ->latest('id')
                ->first();

            if ($cached) {
                // ایجاد یک run جدید که به cache اشاره می‌کند
                return $this->createCacheHitRun($report, $input, $requestedBy, $cached);
            }
        }

        // 2. ایجاد run جدید
        $run = ReportRun::create([
            'report_id' => $report->id,
            'organization_id' => $input->organizationId ?? $report->organization_id,
            'requested_by_user_id' => $requestedBy?->id,
            'input_params' => $input->toArray(),
            'params_hash' => $paramsHash,
            'status' => ReportRunStatus::Queued,
        ]);

        // 3. اجرای synchronous
        // (در فاز ۶ async via queue اضافه می‌شود اگر گزارش سنگین است)
        return $this->execute($run, $report, $input, $requestedBy);
    }

    /**
     * اجرای واقعی handler.
     *
     * این متد ممکن است از run() جداگانه فراخوانی شود (مثلاً از job).
     */
    public function execute(
        ReportRun $run,
        Report $report,
        ReportInput $input,
        ?User $requestedBy = null,
    ): ReportRun {
        $run->markStarted();

        try {
            $handler = $report->makeHandler();
            $result = $handler->run($input, $requestedBy);

            $cacheUntil = null;
            if ($report->is_cacheable && $report->cache_ttl_minutes > 0) {
                $cacheUntil = now()->addMinutes($report->cache_ttl_minutes);
            }

            $run->markCompleted(
                resultData: $result->toArray(),
                rowCount: $result->rowCount(),
                cacheUntil: $cacheUntil,
            );

            $this->auditService->log(
                event: 'report_executed',
                auditable: $run,
                description: sprintf(
                    'گزارش "%s" با %d رکورد در %d ms اجرا شد',
                    $report->display_name,
                    $result->rowCount(),
                    $run->fresh()->duration_ms ?? 0,
                ),
                context: ['report_key' => $report->key, 'params_hash' => $run->params_hash],
                severity: 'info',
            );
        } catch (\Throwable $e) {
            Log::error("Report execution failed: {$report->key}", [
                'run_id' => $run->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $run->markFailed($e->getMessage());

            $this->auditService->log(
                event: 'report_failed',
                auditable: $run,
                description: "اجرای گزارش «{$report->display_name}» ناموفق بود: " . $e->getMessage(),
                severity: 'error',
            );

            throw ReportException::executionFailed($report->key, $e->getMessage());
        }

        return $run->fresh();
    }

    private function createCacheHitRun(
        Report $report,
        ReportInput $input,
        ?User $requestedBy,
        ReportRun $cached,
    ): ReportRun {
        $run = ReportRun::create([
            'report_id' => $report->id,
            'organization_id' => $input->organizationId ?? $report->organization_id,
            'requested_by_user_id' => $requestedBy?->id,
            'input_params' => $input->toArray(),
            'params_hash' => $input->hash(),
            'status' => ReportRunStatus::Completed,
            'started_at' => now(),
            'completed_at' => now(),
            'duration_ms' => 0,
            'result_data' => $cached->result_data,
            'row_count' => $cached->row_count,
            'cached_until' => $cached->cached_until,
            'metadata' => ['cache_hit' => true, 'source_run_id' => $cached->id],
        ]);

        return $run;
    }

    /**
     * Cache یک گزارش را invalidate می‌کند — برای زمانی که داده‌های source تغییر کرده.
     */
    public function invalidateCache(Report $report, ?string $paramsHash = null): int
    {
        $query = ReportRun::query()
            ->where('report_id', $report->id)
            ->whereNotNull('cached_until')
            ->where('cached_until', '>', now());

        if ($paramsHash) {
            $query->where('params_hash', $paramsHash);
        }

        return $query->update(['cached_until' => now()->subSecond()]);
    }
}
