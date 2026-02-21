<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Class RolesAndPermissionsSeeder
 *
 * تعریف تمام نقش‌ها و دسترسی‌های پایه سامانه.
 *
 * این Seeder idempotent است: اجرای مجدد آن مشکل ایجاد نمی‌کند.
 * نقش‌های موجود به‌روز می‌شوند، نقش‌های جدید اضافه می‌شوند.
 *
 * ساختار permission name: "{domain}.{action}"
 * مثال: user.view, meeting.create, minutes.sign
 */
class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * تعریف permission ها به تفکیک دامنه
     * هر کلید دامنه است و مقدار آرایه‌ای از action ها.
     */
    private array $permissionsByDomain = [
        // Identity domain
        'user' => ['view', 'create', 'update', 'delete', 'suspend', 'unlock', 'assign-role', 'reset-password'],
        'role' => ['view', 'create', 'update', 'delete', 'assign-permission'],
        'permission' => ['view'],
        'delegation' => ['view', 'create', 'revoke'],

        // Organization domain
        'organization' => ['view', 'create', 'update', 'delete'],
        'orgunit' => ['view', 'create', 'update', 'delete', 'move'],
        'position' => ['view', 'create', 'update', 'delete', 'freeze', 'abolish'],
        'jobtitle' => ['view', 'create', 'update', 'delete'],
        'employee' => ['view', 'create', 'update', 'delete', 'transfer', 'assign-position'],

        // Audit domain
        'auditlog' => ['view', 'export'],
        'loginlog' => ['view', 'export'],
        'securityevent' => ['view', 'review'],

        // Settings domain
        'setting' => ['view', 'update'],

        // Phase 2 — Meetings & Rooms
        'meeting' => ['view', 'view_own', 'view_all', 'view-confidential', 'create', 'update', 'delete', 'cancel', 'reschedule', 'record_attendance', 'send-invitations', 'sign-minutes', 'approve-minutes'],
        'room' => ['view', 'create', 'update', 'delete', 'manage', 'approve-reservation'],
        'reservation' => ['view', 'create', 'update', 'cancel', 'approve', 'manage', 'override'],

        // Phase 3 — Post-meeting: Minutes, Resolutions, Tasks, Files, Notifications
        'minute' => ['view', 'view_all', 'create', 'update', 'delete', 'sign-secretary', 'sign-chairperson', 'sign-other', 'publish', 'revoke'],
        'resolution' => ['view', 'view_all', 'create', 'update', 'delete', 'vote', 'close-voting'],
        'task' => ['view', 'view_all', 'create', 'update', 'delete', 'assign', 'approve', 'extend'],
        'file' => ['upload', 'view', 'view_all', 'delete_any'],
        'notification' => ['manage_templates'],

        // Phase 4 — Workflow (BPMN engine)
        'process' => ['view', 'view_all', 'create', 'update', 'deploy', 'publish', 'archive', 'delete'],
        'process_instance' => ['view', 'view_all', 'suspend', 'cancel', 'retry'],
        'user_task' => ['view', 'view_all', 'claim', 'complete', 'reassign'],
        'workflow' => ['view', 'design', 'publish', 'monitor', 'view_designer'],

        // Phase 5 — VideoConference & ServiceRequests
        'vc_provider' => ['view', 'manage', 'health_check'],
        'vc_room' => ['view', 'view_all', 'create', 'end', 'record', 'join'],
        'service_request' => ['view', 'view_all', 'create', 'update_any', 'submit', 'review', 'assign', 'complete', 'cancel_any', 'delete'],

        // Phase 6 — Reports, Dashboards, Integrations, API
        'report' => ['view', 'view_all', 'run', 'create', 'update', 'delete', 'schedule', 'export'],
        'dashboard' => ['view', 'manage'],
        'integration' => ['view', 'view_all', 'manage', 'sync', 'test'],
        'webhook' => ['view', 'manage'],
        'export' => ['create', 'view_all', 'download'],
        'api' => ['access', 'manage_tokens'],
    ];

    /**
     * تعریف نقش‌ها و permission های آن‌ها
     */
    private array $roles = [
        'super-admin' => [
            'display_name' => 'مدیر ارشد سامانه',
            'description' => 'دسترسی کامل به همه بخش‌های سامانه — قابل لغو نیست',
            'priority' => 1000,
            'is_system' => true,
            'permissions' => '*', // همه
        ],
        'system-admin' => [
            'display_name' => 'مدیر سیستم',
            'description' => 'مدیریت تنظیمات، کاربران، نقش‌ها و امنیت سامانه',
            'priority' => 900,
            'is_system' => true,
            'permissions' => [
                'user.*', 'role.*', 'permission.*', 'delegation.*',
                'setting.*', 'auditlog.view', 'loginlog.view',
                'securityevent.*',
            ],
        ],
        'organization-admin' => [
            'display_name' => 'مدیر سازمان',
            'description' => 'مدیریت ساختار سازمانی، پست‌ها، کارمندان',
            'priority' => 800,
            'is_system' => true,
            'permissions' => [
                'organization.*', 'orgunit.*', 'position.*', 'jobtitle.*',
                'employee.*', 'user.view', 'user.create', 'user.update',
                'delegation.view',
                // Phase 6
                'report.view', 'report.run', 'report.schedule', 'report.export',
                'dashboard.view', 'dashboard.manage',
                'integration.view', 'integration.manage', 'integration.sync',
                'webhook.view', 'webhook.manage',
                'export.create', 'export.download',
                'api.access', 'api.manage_tokens',
            ],
        ],
        'auditor' => [
            'display_name' => 'ممیز',
            'description' => 'دسترسی فقط-خواندنی به لاگ‌ها و گزارش‌های امنیتی',
            'priority' => 700,
            'is_system' => true,
            'permissions' => [
                'auditlog.view', 'auditlog.export',
                'loginlog.view', 'loginlog.export',
                'securityevent.view', 'securityevent.review',
                'user.view', 'orgunit.view', 'employee.view',
                // Phase 6
                'report.view', 'report.view_all', 'report.run', 'report.export',
                'dashboard.view',
                'export.create', 'export.download',
            ],
        ],

        // نقش‌های فاز ۲ (placeholder)
        'meeting-creator' => [
            'display_name' => 'ایجادکننده جلسه',
            'description' => 'می‌تواند جلسه ایجاد، ویرایش و لغو کند',
            'priority' => 500,
            'is_system' => false,
            'permissions' => [
                'meeting.view', 'meeting.create', 'meeting.update', 'meeting.cancel',
                'reservation.create', 'reservation.view',
                'employee.view', 'orgunit.view',
            ],
        ],
        'meeting-secretary' => [
            'display_name' => 'دبیر جلسه',
            'description' => 'مدیریت کامل جلسات اختصاص داده شده، صورتجلسه، مصوبات',
            'priority' => 600,
            'is_system' => false,
            'permissions' => [
                'meeting.*', 'reservation.*', 'task.*',
                'employee.view', 'orgunit.view',
                // Phase 6
                'report.view', 'report.run', 'report.export',
                'dashboard.view',
                'export.create', 'export.download',
            ],
        ],
        'meeting-chairperson' => [
            'display_name' => 'رئیس جلسه',
            'description' => 'تأیید مصوبات، امضای صورتجلسه',
            'priority' => 650,
            'is_system' => false,
            'permissions' => [
                'meeting.view', 'meeting.view-confidential',
                'meeting.sign-minutes', 'meeting.approve-minutes',
                'task.approve', 'employee.view',
            ],
        ],
        'invitee' => [
            'display_name' => 'مدعو',
            'description' => 'مدعو جلسه — می‌تواند پاسخ دعوت دهد',
            'priority' => 100,
            'is_system' => true,
            'permissions' => [
                'meeting.view',
            ],
        ],
        'room-manager' => [
            'display_name' => 'مدیر سالن',
            'description' => 'تأیید/رد رزرو سالن',
            'priority' => 400,
            'is_system' => false,
            'permissions' => [
                'room.*', 'reservation.view', 'reservation.approve',
            ],
        ],
        'task-assignee' => [
            'display_name' => 'مجری وظیفه',
            'description' => 'مجری مصوبات و وظایف',
            'priority' => 200,
            'is_system' => false,
            'permissions' => [
                'task.view', 'task.update', 'task.extend',
            ],
        ],
        'process-admin' => [
            'display_name' => 'مدیر فرایند',
            'description' => 'طراحی و انتشار فرایندهای BPMN',
            'priority' => 750,
            'is_system' => false,
            'permissions' => [
                'workflow.*', 'auditlog.view',
            ],
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        DB::transaction(function () {
            $this->createPermissions();
            $this->createRoles();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function createPermissions(): void
    {
        foreach ($this->permissionsByDomain as $domain => $actions) {
            foreach ($actions as $action) {
                $name = "{$domain}.{$action}";
                Permission::firstOrCreate(
                    ['name' => $name, 'guard_name' => 'web'],
                    [
                        'category' => $domain,
                        'display_name' => $this->generateDisplayName($domain, $action),
                        'is_system' => true,
                    ]
                );
            }
        }
    }

    private function createRoles(): void
    {
        foreach ($this->roles as $name => $config) {
            $role = Role::updateOrCreate(
                ['name' => $name, 'guard_name' => 'web', 'organization_id' => null],
                [
                    'display_name' => $config['display_name'],
                    'description' => $config['description'],
                    'priority' => $config['priority'],
                    'is_system' => $config['is_system'],
                    'is_assignable' => true,
                ]
            );

            $permissionsToSync = $this->resolvePermissions($config['permissions']);
            $role->syncPermissions($permissionsToSync);
        }
    }

    /**
     * resolve یک permission specification به permission های واقعی
     * '*' = همه
     * 'user.*' = همه action های user
     * ['a', 'b'] = دقیقاً این‌ها
     */
    private function resolvePermissions(array|string $spec): array
    {
        if ($spec === '*') {
            return Permission::pluck('name')->toArray();
        }

        $resolved = [];
        foreach ((array) $spec as $pattern) {
            if (str_ends_with($pattern, '.*')) {
                $prefix = rtrim($pattern, '*');
                $matching = Permission::where('name', 'like', $prefix . '%')->pluck('name')->toArray();
                $resolved = array_merge($resolved, $matching);
            } else {
                if (Permission::where('name', $pattern)->exists()) {
                    $resolved[] = $pattern;
                }
            }
        }

        return array_unique($resolved);
    }

    private function generateDisplayName(string $domain, string $action): string
    {
        $domainNames = [
            'user' => 'کاربر',
            'role' => 'نقش',
            'permission' => 'دسترسی',
            'delegation' => 'تفویض',
            'organization' => 'سازمان',
            'orgunit' => 'واحد سازمانی',
            'position' => 'پست',
            'jobtitle' => 'عنوان شغلی',
            'employee' => 'کارمند',
            'auditlog' => 'لاگ ممیزی',
            'loginlog' => 'لاگ ورود',
            'securityevent' => 'رویداد امنیتی',
            'setting' => 'تنظیمات',
            'meeting' => 'جلسه',
            'room' => 'سالن',
            'reservation' => 'رزرو',
            'task' => 'وظیفه',
            'minute' => 'صورتجلسه',
            'resolution' => 'مصوبه',
            'file' => 'فایل',
            'notification' => 'اعلان',
            'workflow' => 'فرایند',
            'report' => 'گزارش',
            // Phase 6
            'dashboard' => 'داشبورد',
            'integration' => 'یکپارچه‌سازی',
            'webhook' => 'Webhook',
            'export' => 'Export',
            'api' => 'API',
        ];

        $actionNames = [
            'view' => 'مشاهده',
            'view-confidential' => 'مشاهده محرمانه',
            'create' => 'ایجاد',
            'update' => 'ویرایش',
            'delete' => 'حذف',
            'suspend' => 'تعلیق',
            'unlock' => 'بازکردن',
            'assign-role' => 'انتساب نقش',
            'reset-password' => 'تغییر رمز',
            'assign-permission' => 'تخصیص دسترسی',
            'revoke' => 'لغو',
            'move' => 'انتقال',
            'freeze' => 'فریز',
            'abolish' => 'منحل',
            'transfer' => 'انتقال',
            'assign-position' => 'انتساب پست',
            'export' => 'خروجی',
            'review' => 'بررسی',
            'cancel' => 'لغو',
            'reschedule' => 'تغییر زمان',
            'sign-minutes' => 'امضای صورتجلسه',
            'approve-minutes' => 'تأیید صورتجلسه',
            'approve-reservation' => 'تأیید رزرو',
            'approve' => 'تأیید',
            'override' => 'override',
            'assign' => 'ارجاع',
            'extend' => 'تمدید',
            'design' => 'طراحی',
            'publish' => 'انتشار',
            'monitor' => 'مانیتورینگ',
            'view_all' => 'مشاهده همه',
            'view_own' => 'مشاهده خودی',
            'sign-secretary' => 'امضای دبیر',
            'sign-chairperson' => 'امضای رئیس',
            'sign-other' => 'امضای دیگر',
            'vote' => 'رأی‌گیری',
            'close-voting' => 'بستن رأی‌گیری',
            'upload' => 'بارگذاری',
            'delete_any' => 'حذف هر',
            'manage_templates' => 'مدیریت قالب‌ها',
            'record_attendance' => 'ثبت حضور',
            'send-invitations' => 'ارسال دعوت',
            'manage' => 'مدیریت',
            'deploy' => 'استقرار',
            'archive' => 'بایگانی',
            'retry' => 'تلاش مجدد',
            'claim' => 'تحویل گرفتن',
            'complete' => 'تکمیل',
            'reassign' => 'انتقال',
            'view_designer' => 'مشاهده Designer',
            'suspend' => 'توقف',
            // Phase 5
            'health_check' => 'بررسی سلامت',
            'join' => 'پیوستن',
            'record' => 'ضبط',
            'submit' => 'ارسال',
            'update_any' => 'ویرایش هر',
            'cancel_any' => 'لغو هر',
            'end' => 'پایان',
            // Phase 6
            'run' => 'اجرا',
            'schedule' => 'زمان‌بندی',
            'sync' => 'همگام‌سازی',
            'test' => 'تست',
            'download' => 'دانلود',
            'access' => 'دسترسی',
            'manage_tokens' => 'مدیریت Tokens',
        ];

        $domainName = $domainNames[$domain] ?? $domain;
        $actionName = $actionNames[$action] ?? $action;

        return "{$actionName} {$domainName}";
    }
}
