<?php

declare(strict_types=1);

namespace App\Domains\Exports\Services;

use App\Domains\Exports\Enums\ExportStatus;
use App\Domains\Exports\Models\ExportJob;
use Illuminate\Support\Facades\Storage;

/**
 * ExportCleanupService — حذف export های منقضی.
 *
 * توسط دستور scheduled `exports:cleanup-expired` (روزانه) فراخوانی می‌شود.
 */
class ExportCleanupService
{
    public function cleanup(): array
    {
        $expired = ExportJob::query()
            ->expired()
            ->whereIn('status', [ExportStatus::Completed])
            ->with('outputFile')
            ->get();

        $deletedFiles = 0;
        $markedExpired = 0;

        foreach ($expired as $job) {
            if ($job->outputFile) {
                try {
                    Storage::disk($job->outputFile->disk)->delete($job->outputFile->file_path);
                    $job->outputFile->delete();
                    $deletedFiles++;
                } catch (\Throwable $e) {
                    // ادامه می‌دهیم؛ file ممکن است قبلاً delete شده باشد
                }
            }

            $job->forceFill([
                'status' => ExportStatus::Expired,
                'output_file_id' => null,
            ])->save();

            $markedExpired++;
        }

        return [
            'expired_jobs' => $markedExpired,
            'deleted_files' => $deletedFiles,
        ];
    }
}
