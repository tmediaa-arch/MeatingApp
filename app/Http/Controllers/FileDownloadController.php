<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Files\Models\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * FileDownloadController — تنها endpoint download فایل‌ها.
 *
 * مسئولیت‌ها:
 * - بررسی Authorization (Policy)
 * - ثبت file access log (append-only)
 * - stream فایل از disk (با header های مناسب)
 *
 * این controller برای download همه فایل‌ها استفاده می‌شود:
 * report outputs، meeting attachments، minute attachments، export jobs.
 */
class FileDownloadController extends Controller
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {
    }

    public function __invoke(Request $request, int $file): StreamedResponse
    {
        $fileModel = File::findOrFail($file);

        // Policy check
        $this->authorize('view', $fileModel);

        $disk = Storage::disk($fileModel->disk);

        if (!$disk->exists($fileModel->file_path)) {
            abort(404, 'فایل در storage یافت نشد.');
        }

        // ثبت access log
        $this->auditService->log(
            event: 'file_downloaded',
            auditable: $fileModel,
            description: "فایل «{$fileModel->title}» دانلود شد",
            context: [
                'file_name' => $fileModel->original_name,
                'size_bytes' => $fileModel->file_size_bytes,
                'ip' => $request->ip(),
            ],
            severity: 'info',
        );

        return $disk->download(
            $fileModel->file_path,
            $fileModel->original_name ?? $fileModel->file_name,
            [
                'Content-Type' => $fileModel->mime_type,
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
