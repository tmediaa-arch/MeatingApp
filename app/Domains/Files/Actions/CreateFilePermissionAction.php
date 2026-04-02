<?php

declare(strict_types=1);

namespace App\Domains\Files\Actions;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Files\Exceptions\FileException;
use App\Domains\Files\Models\File;
use App\Domains\Files\Models\FilePermission;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * CreateFilePermissionAction — اعطای مجوز صریح روی یک فایل.
 *
 * مجوز می‌تواند برای یک کاربر خاص (user_id)، یک نقش (role_id) یا یک
 * واحد سازمانی (org_unit_id) صادر شود و می‌تواند موقت (expires_at) باشد.
 */
class CreateFilePermissionAction
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {
    }

    /**
     * @param  File  $file
     * @param  User  $grantedBy  کاربر اعطا کننده
     * @param  array  $params  user_id|role_id|org_unit_id, can_view, can_download, can_share, expires_at, notes
     */
    public function execute(File $file, User $grantedBy, array $params): FilePermission
    {
        if (empty($params['user_id']) && empty($params['role_id']) && empty($params['org_unit_id'])) {
            throw new \InvalidArgumentException('حداقل یکی از user_id، role_id یا org_unit_id باید مشخص شود.');
        }

        return DB::transaction(function () use ($file, $grantedBy, $params) {
            // بررسی تکراری نبودن
            $existing = FilePermission::query()
                ->where('file_id', $file->id)
                ->when(!empty($params['user_id']), fn ($q) => $q->where('user_id', $params['user_id']))
                ->when(!empty($params['role_id']), fn ($q) => $q->where('role_id', $params['role_id']))
                ->when(!empty($params['org_unit_id']), fn ($q) => $q->where('org_unit_id', $params['org_unit_id']))
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->first();

            if ($existing) {
                throw FileException::permissionAlreadyExists();
            }

            $permission = FilePermission::create([
                'file_id' => $file->id,
                'user_id' => $params['user_id'] ?? null,
                'role_id' => $params['role_id'] ?? null,
                'org_unit_id' => $params['org_unit_id'] ?? null,
                'can_view' => $params['can_view'] ?? true,
                'can_download' => $params['can_download'] ?? false,
                'can_share' => $params['can_share'] ?? false,
                'granted_by_user_id' => $grantedBy->id,
                'expires_at' => $params['expires_at'] ?? null,
                'notes' => $params['notes'] ?? null,
            ]);

            $subject = match (true) {
                !empty($params['user_id']) => "کاربر #{$params['user_id']}",
                !empty($params['role_id']) => "نقش #{$params['role_id']}",
                !empty($params['org_unit_id']) => "واحد #{$params['org_unit_id']}",
            };

            $this->auditService->log(
                event: 'file_permission_granted',
                auditable: $file,
                description: sprintf(
                    'مجوز دسترسی به فایل "%s" برای %s صادر شد',
                    $file->title ?? $file->original_name,
                    $subject,
                ),
                newValues: $permission->only([
                    'user_id', 'role_id', 'org_unit_id',
                    'can_view', 'can_download', 'can_share', 'expires_at',
                ]),
                severity: 'info',
            );

            return $permission;
        });
    }
}
