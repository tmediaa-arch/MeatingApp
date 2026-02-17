<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Identity\Models\User;
use App\Domains\Organization\Models\OrgUnit;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Class AssignRoleAction
 *
 * انتساب نقش به کاربر — با امکان scope-restricted (فقط در یک واحد سازمانی)
 * و bounded time (با تاریخ شروع و پایان).
 *
 * این Action با گسترش spatie/permission کار می‌کند که در migration
 * ستون‌های org_unit_id, valid_from, valid_until به model_has_roles اضافه کرده.
 */
class AssignRoleAction
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {
    }

    public function execute(
        User $user,
        string|Role $role,
        ?OrgUnit $orgUnit = null,
        ?\DateTimeInterface $validFrom = null,
        ?\DateTimeInterface $validUntil = null,
        ?string $assignmentReason = null,
    ): void {
        $roleModel = $role instanceof Role ? $role : Role::findByName($role);

        DB::transaction(function () use ($user, $roleModel, $orgUnit, $validFrom, $validUntil, $assignmentReason) {
            // اول بررسی می‌کنیم رکورد مشابه (همان role + همان org_unit) قبلاً وجود ندارد
            $exists = DB::table('model_has_roles')
                ->where('role_id', $roleModel->id)
                ->where('model_id', $user->id)
                ->where('model_type', $user->getMorphClass())
                ->where('org_unit_id', $orgUnit?->id ?? 0)
                ->exists();

            if ($exists) {
                // به‌روزرسانی valid_from/until
                DB::table('model_has_roles')
                    ->where('role_id', $roleModel->id)
                    ->where('model_id', $user->id)
                    ->where('model_type', $user->getMorphClass())
                    ->where('org_unit_id', $orgUnit?->id ?? 0)
                    ->update([
                        'valid_from' => $validFrom,
                        'valid_until' => $validUntil,
                        'assigned_by' => auth()->id(),
                        'assigned_at' => now(),
                        'assignment_reason' => $assignmentReason,
                    ]);
            } else {
                DB::table('model_has_roles')->insert([
                    'role_id' => $roleModel->id,
                    'model_id' => $user->id,
                    'model_type' => $user->getMorphClass(),
                    'org_unit_id' => $orgUnit?->id ?? 0, // 0 یعنی scope ندارد
                    'valid_from' => $validFrom,
                    'valid_until' => $validUntil,
                    'assigned_by' => auth()->id(),
                    'assigned_at' => now(),
                    'assignment_reason' => $assignmentReason,
                ]);
            }

            // پاک کردن cache spatie/permission
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

            $this->auditService->log(
                event: 'role_assigned',
                auditable: $user,
                description: "نقش '{$roleModel->name}' به کاربر '{$user->username}' داده شد"
                    . ($orgUnit ? " (محدوده: {$orgUnit->name})" : ''),
                context: [
                    'role_id' => $roleModel->id,
                    'role_name' => $roleModel->name,
                    'org_unit_id' => $orgUnit?->id,
                    'valid_from' => $validFrom?->format('Y-m-d H:i:s'),
                    'valid_until' => $validUntil?->format('Y-m-d H:i:s'),
                    'reason' => $assignmentReason,
                ],
                severity: 'notice',
            );
        });
    }
}
