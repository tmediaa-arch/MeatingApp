<?php

declare(strict_types=1);

namespace App\Domains\Exports\Services;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Exports\Contracts\ExportGeneratorInterface;
use App\Domains\Exports\Enums\ExportStatus;
use App\Domains\Exports\Generators\CalendarIcsGenerator;
use App\Domains\Exports\Generators\MeetingsExcelGenerator;
use App\Domains\Exports\Generators\MinutesPdfGenerator;
use App\Domains\Exports\Generators\TasksCsvGenerator;
use App\Domains\Exports\Models\ExportJob;
use App\Domains\Files\Models\File;
use App\Domains\Reports\Models\Report;
use Illuminate\Support\Facades\Storage;

/**
 * ExportService — هسته پردازش export های سیستم.
 *
 * این سرویس:
 * 1. لیست generatorها را نگه می‌دارد
 * 2. برای یک ExportJob، generator مناسب را پیدا و اجرا می‌کند
 * 3. خروجی را در File domain ذخیره می‌کند
 * 4. ExportJob را به completed/failed تغییر می‌دهد
 */
class ExportService
{
    /**
     * @var array<int, class-string<ExportGeneratorInterface>>
     */
    private array $generators = [];

    public function __construct(
        private readonly AuditService $auditService,
    ) {
        // generator های پیش‌فرض
        $this->register(MeetingsExcelGenerator::class);
        $this->register(CalendarIcsGenerator::class);
        $this->register(TasksCsvGenerator::class);
        $this->register(MinutesPdfGenerator::class);
    }

    public function register(string $generatorClass): void
    {
        if (!is_subclass_of($generatorClass, ExportGeneratorInterface::class)) {
            throw new \LogicException("{$generatorClass} باید ExportGeneratorInterface را پیاده‌سازی کند.");
        }
        $this->generators[] = $generatorClass;
    }

    public function process(ExportJob $job): ExportJob
    {
        $job->markStarted();

        try {
            $generator = $this->findGenerator($job);
            if (!$generator) {
                throw new \DomainException(
                    "Generator برای export_type={$job->export_type->value} و format={$job->format} یافت نشد."
                );
            }

            $result = $generator->generate($job);

            // ذخیره فایل خروجی در File domain
            $directory = sprintf('exports/%s/%s', $job->organization_id ?? 'system', now()->format('Y/m'));
            $path = $directory . '/' . $result['filename'];

            Storage::disk('local')->put($path, $result['content']);

            $file = File::create([
                'organization_id' => $job->organization_id,
                'owner_type' => ExportJob::class,
                'owner_id' => $job->id,
                'title' => $job->label ?? $job->export_type->label(),
                'description' => 'خروجی export #' . $job->id,
                'disk' => 'local',
                'file_path' => $path,
                'file_name' => $result['filename'],
                'original_name' => $result['filename'],
                'mime_type' => $result['mime'],
                'extension' => $result['extension'],
                'file_size_bytes' => strlen($result['content']),
                'file_hash_sha256' => hash('sha256', $result['content']),
                'file_hash_md5' => md5($result['content']),
                'is_encrypted' => false,
                'has_watermark' => false,
                'category' => 'export_output',
                'confidentiality_level' => 'internal',
                'version' => 1,
                'is_ocred' => false,
                'virus_scan_status' => 'clean',
                'tags' => ['export', $job->export_type->value],
                'uploaded_by_user_id' => $job->requested_by_user_id,
            ]);

            $job->markCompleted($file, $result['row_count'] ?? 0);

            $this->auditService->log(
                event: 'export_completed',
                auditable: $job,
                description: sprintf(
                    'Export %s (%s) با %d رکورد تکمیل شد',
                    $job->export_type->label(),
                    $job->format,
                    $result['row_count'] ?? 0,
                ),
                severity: 'info',
            );
        } catch (\Throwable $e) {
            $job->markFailed($e->getMessage());

            $this->auditService->log(
                event: 'export_failed',
                auditable: $job,
                description: "Export ناموفق: " . $e->getMessage(),
                severity: 'error',
            );

            throw $e;
        }

        return $job->fresh();
    }

    private function findGenerator(ExportJob $job): ?ExportGeneratorInterface
    {
        foreach ($this->generators as $class) {
            /** @var ExportGeneratorInterface $instance */
            $instance = app($class);
            if ($instance->supports($job)) {
                return $instance;
            }
        }
        return null;
    }
}
