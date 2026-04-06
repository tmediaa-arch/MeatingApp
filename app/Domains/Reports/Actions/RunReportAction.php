<?php

declare(strict_types=1);

namespace App\Domains\Reports\Actions;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Files\Actions\UploadFileAction;
use App\Domains\Files\Models\File;
use App\Domains\Identity\Models\User;
use App\Domains\Reports\DTOs\ReportInput;
use App\Domains\Reports\Enums\ReportFormat;
use App\Domains\Reports\Exceptions\ReportException;
use App\Domains\Reports\Models\Report;
use App\Domains\Reports\Models\ReportRun;
use App\Domains\Reports\Services\ReportRenderingService;
use App\Domains\Reports\Services\ReportRunnerService;
use App\Domains\Shared\Enums\ConfidentialityLevel;
use Illuminate\Support\Facades\Storage;

/**
 * RunReportAction — اجرا و render یک گزارش به فرمت دلخواه.
 *
 * این Action ترکیبی:
 * 1. ReportRunnerService::run() را صدا می‌زند
 * 2. ReportRenderingService::render() را برای فرمت خروجی صدا می‌زند
 * 3. اگر فرمت غیر HTML است، خروجی به File domain ذخیره می‌شود و
 *    output_file_id در ReportRun ست می‌شود
 */
class RunReportAction
{
    public function __construct(
        private readonly ReportRunnerService $runner,
        private readonly ReportRenderingService $renderer,
        private readonly AuditService $auditService,
    ) {
    }

    public function execute(
        Report $report,
        array $params,
        ?User $user = null,
        ReportFormat $format = ReportFormat::Html,
        bool $forceFresh = false,
    ): ReportRun {
        if (!$report->supportsFormat($format->value)) {
            throw ReportException::unsupportedFormat($report->key, $format->value);
        }

        $input = ReportInput::fromArray($params);

        // 1. اجرای گزارش
        $run = $this->runner->run($report, $input, $user, $forceFresh);

        // 2. اگر فرمت غیر در-حافظه است، خروجی را به فایل تبدیل کن
        if ($format !== ReportFormat::Html && $format !== ReportFormat::Json) {
            $rendered = $this->renderer->render($report, $run, $format);

            // ذخیره روی disk از طریق UploadFileAction نیاز به UploadedFile دارد؛
            // اینجا مستقیماً Storage و File را استفاده می‌کنیم
            $orgId = $input->organizationId ?? $report->organization_id;
            $directory = sprintf('reports/%s/%s', $orgId ?? 'system', now()->format('Y/m'));
            $path = $directory . '/' . $rendered['filename'];

            Storage::disk('local')->put($path, $rendered['content']);

            $file = File::create([
                'organization_id' => $orgId,
                'owner_type' => Report::class,
                'owner_id' => $report->id,
                'title' => $report->display_name,
                'description' => 'خروجی گزارش — اجرای #' . $run->id,
                'disk' => 'local',
                'file_path' => $path,
                'file_name' => $rendered['filename'],
                'original_name' => $rendered['filename'],
                'mime_type' => $rendered['mime'],
                'extension' => $rendered['extension'],
                'file_size_bytes' => strlen($rendered['content']),
                'file_hash_sha256' => hash('sha256', $rendered['content']),
                'file_hash_md5' => md5($rendered['content']),
                'is_encrypted' => false,
                'has_watermark' => false,
                'category' => 'report_output',
                'confidentiality_level' => $report->confidentiality_level,
                'version' => 1,
                'is_ocred' => false,
                'virus_scan_status' => 'clean',
                'tags' => ['report', $report->key],
                'uploaded_by_user_id' => $user?->id,
            ]);

            $run->forceFill([
                'output_file_id' => $file->id,
                'output_format' => $format->value,
            ])->save();
        }

        $this->auditService->log(
            event: 'report_run',
            auditable: $run,
            description: "گزارش «{$report->display_name}» در فرمت {$format->label()} اجرا شد",
            context: ['report_key' => $report->key, 'format' => $format->value],
            severity: 'info',
        );

        return $run->fresh();
    }
}
