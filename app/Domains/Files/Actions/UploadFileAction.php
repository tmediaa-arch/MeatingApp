<?php

declare(strict_types=1);

namespace App\Domains\Files\Actions;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Files\Exceptions\FileException;
use App\Domains\Files\Models\File;
use App\Domains\Identity\Models\User;
use App\Domains\Shared\Enums\ConfidentialityLevel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * UploadFileAction — تنها نقطه ورود برای آپلود فایل به سامانه.
 *
 * این Action مسئول است:
 * - محاسبه hash (sha256 + md5) برای یکپارچگی و تشخیص duplicate
 * - شناسایی mime type و extension
 * - ذخیره روی disk مناسب
 * - ایجاد رکورد File با metadata کامل
 * - audit log
 */
class UploadFileAction
{
    /**
     * انواع MIME مجاز به صورت پیش‌فرض (در صورت نیاز قابل تنظیم در config).
     */
    private const DEFAULT_ALLOWED_MIMES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'text/plain',
        'text/csv',
        'application/zip',
        'application/x-rar-compressed',
    ];

    private const DEFAULT_MAX_SIZE_BYTES = 52_428_800; // 50 MB

    public function __construct(
        private readonly AuditService $auditService,
    ) {
    }

    /**
     * @param  UploadedFile  $uploaded  فایل دریافت شده از request
     * @param  User  $uploader  کاربر آپلود کننده
     * @param  array  $options  options: organization_id, owner (Model), category, confidentiality_level, title, description, tags, expires_at, allowed_mimes, max_size_bytes, disk
     *
     * @throws FileException
     */
    public function execute(UploadedFile $uploaded, User $uploader, array $options = []): File
    {
        // ── ولیدیشن
        $allowedMimes = $options['allowed_mimes'] ?? self::DEFAULT_ALLOWED_MIMES;
        $maxSize = $options['max_size_bytes'] ?? self::DEFAULT_MAX_SIZE_BYTES;

        $mime = $uploaded->getMimeType() ?? 'application/octet-stream';
        if (!in_array($mime, $allowedMimes, true)) {
            throw FileException::unsupportedMimeType($mime);
        }

        $size = $uploaded->getSize() ?? 0;
        if ($size > $maxSize) {
            throw FileException::fileTooLarge($size, $maxSize);
        }

        // ── محاسبه hash برای یکپارچگی + تشخیص duplicate
        $localPath = $uploaded->getRealPath();
        $hashSha256 = hash_file('sha256', $localPath);
        $hashMd5 = hash_file('md5', $localPath);

        $disk = $options['disk'] ?? config('filesystems.default', 'local');

        return DB::transaction(function () use (
            $uploaded, $uploader, $options, $mime, $size, $hashSha256, $hashMd5, $disk
        ) {
            // ── ذخیره روی disk
            $originalName = $uploaded->getClientOriginalName();
            $extension = $uploaded->getClientOriginalExtension() ?: $uploaded->guessExtension() ?: '';
            $fileName = sprintf('%s_%s.%s',
                now()->format('YmdHis'),
                substr($hashSha256, 0, 8),
                $extension
            );

            $orgId = $options['organization_id']
                ?? $uploader->employee?->organization_id
                ?? null;

            $directory = sprintf('files/%s/%s',
                $orgId ?? 'system',
                now()->format('Y/m')
            );

            $storedPath = $uploaded->storeAs($directory, $fileName, $disk);
            if ($storedPath === false) {
                throw FileException::uploadFailed('storage write failed');
            }

            // ── verify integrity پس از write
            if (Storage::disk($disk)->exists($storedPath)) {
                $diskHash = hash('sha256', Storage::disk($disk)->get($storedPath));
                if ($diskHash !== $hashSha256) {
                    Storage::disk($disk)->delete($storedPath);
                    throw FileException::hashMismatch();
                }
            }

            // ── متادیتای owner
            $ownerType = null;
            $ownerId = null;
            if (isset($options['owner']) && $options['owner']) {
                $ownerType = get_class($options['owner']);
                $ownerId = $options['owner']->getKey();
            }

            $confidentiality = $options['confidentiality_level'] ?? ConfidentialityLevel::Internal;
            if (is_string($confidentiality)) {
                $confidentiality = ConfidentialityLevel::from($confidentiality);
            }

            $file = File::create([
                'organization_id' => $orgId,
                'owner_type' => $ownerType,
                'owner_id' => $ownerId,
                'title' => $options['title'] ?? pathinfo($originalName, PATHINFO_FILENAME),
                'description' => $options['description'] ?? null,
                'disk' => $disk,
                'file_path' => $storedPath,
                'file_name' => $fileName,
                'original_name' => $originalName,
                'mime_type' => $mime,
                'extension' => $extension,
                'file_size_bytes' => $size,
                'file_hash_sha256' => $hashSha256,
                'file_hash_md5' => $hashMd5,
                'is_encrypted' => false,
                'has_watermark' => false,
                'category' => $options['category'] ?? 'general',
                'confidentiality_level' => $confidentiality,
                'version' => 1,
                'is_ocred' => false,
                'virus_scan_status' => 'pending', // در Phase آینده توسط service خارجی اسکن می‌شود
                'tags' => $options['tags'] ?? [],
                'expires_at' => $options['expires_at'] ?? null,
                'uploaded_by_user_id' => $uploader->id,
            ]);

            $this->auditService->log(
                event: 'file_uploaded',
                auditable: $file,
                description: sprintf(
                    'فایل "%s" (حجم: %s) توسط %s آپلود شد',
                    $originalName,
                    $file->file_size_human,
                    $uploader->name,
                ),
                newValues: [
                    'file_name' => $fileName,
                    'mime_type' => $mime,
                    'size_bytes' => $size,
                    'hash_sha256' => $hashSha256,
                ],
                context: ['owner_type' => $ownerType, 'owner_id' => $ownerId],
                severity: 'info',
            );

            return $file;
        });
    }
}
