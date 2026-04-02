<?php

declare(strict_types=1);

namespace App\Domains\Files\Observers;

use App\Domains\Files\Models\File;
use Illuminate\Support\Facades\Storage;

/**
 * FileObserver — defense-in-depth برای مدل File.
 *
 * - هنگام force-delete، فایل فیزیکی روی disk هم حذف می‌شود
 * - soft-delete فایل فیزیکی را نگه می‌دارد (برای امکان restore)
 */
class FileObserver
{
    public function forceDeleted(File $file): void
    {
        // پاک سازی فایل فیزیکی هنگام حذف نهایی
        if ($file->disk && $file->file_path) {
            try {
                if (Storage::disk($file->disk)->exists($file->file_path)) {
                    Storage::disk($file->disk)->delete($file->file_path);
                }
            } catch (\Throwable $e) {
                // log only — حذف فایل فیزیکی نباید کل تراکنش را شکست بدهد
                logger()->warning("File physical delete failed", [
                    'file_id' => $file->id,
                    'path' => $file->file_path,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function saving(File $file): void
    {
        // اعتبار hash نباید هرگز توسط update به صورت دستی تغییر کند
        if ($file->exists && $file->isDirty('file_hash_sha256')) {
            $original = $file->getOriginal('file_hash_sha256');
            if ($original !== null && $original !== '') {
                throw new \LogicException(
                    'file_hash_sha256 پس از ایجاد قابل تغییر نیست. ' .
                    'برای نسخه جدید، از previous_version_file_id استفاده کنید.'
                );
            }
        }
    }
}
