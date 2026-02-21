<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use App\Domains\Organization\Enums\OrgUnitType;
use App\Domains\Organization\Models\OrgUnit;
use App\Domains\Organization\Models\Organization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Class DefaultOrganizationSeeder
 *
 * - یک سازمان پیش‌فرض ایجاد می‌کند
 * - چند واحد ریشه نمونه می‌سازد
 * - یک کاربر super-admin برای دسترسی اولیه می‌سازد
 *
 * این Seeder فقط در شرایطی اجرا می‌شود که دیتابیس خالی باشد.
 * در محیط production باید این مقادیر تغییر داده شوند.
 */
class DefaultOrganizationSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $org = $this->createDefaultOrganization();
            $this->createInitialOrgStructure($org);
            $this->createSuperAdmin();
        });
    }

    private function createDefaultOrganization(): Organization
    {
        return Organization::firstOrCreate(
            ['code' => 'MAIN'],
            [
                'name' => env('ORG_NAME', 'سازمان نمونه'),
                'short_name' => env('ORG_SHORT_NAME', 'سازمان'),
                'is_active' => true,
            ]
        );
    }

    private function createInitialOrgStructure(Organization $org): void
    {
        // فقط در حالتی که هیچ واحدی موجود نیست
        if ($org->orgUnits()->exists()) {
            return;
        }

        // ساختار نمونه
        $rootCeo = $this->createUnit($org, null, 'CEO', 'مدیرعامل', OrgUnitType::Deputy);

        $itDeputy = $this->createUnit($org, $rootCeo, 'IT-DEP', 'معاونت فناوری اطلاعات', OrgUnitType::Deputy);
        $finDeputy = $this->createUnit($org, $rootCeo, 'FIN-DEP', 'معاونت مالی', OrgUnitType::Deputy);
        $hrDeputy = $this->createUnit($org, $rootCeo, 'HR-DEP', 'معاونت منابع انسانی', OrgUnitType::Deputy);

        $this->createUnit($org, $itDeputy, 'IT-DEV', 'مدیریت توسعه', OrgUnitType::Department);
        $this->createUnit($org, $itDeputy, 'IT-OPS', 'مدیریت زیرساخت', OrgUnitType::Department);
        $this->createUnit($org, $itDeputy, 'IT-SEC', 'مدیریت امنیت', OrgUnitType::Department);

        $this->createUnit($org, $finDeputy, 'FIN-ACC', 'مدیریت حسابداری', OrgUnitType::Department);
        $this->createUnit($org, $finDeputy, 'FIN-TRES', 'مدیریت خزانه‌داری', OrgUnitType::Department);

        $this->createUnit($org, $hrDeputy, 'HR-REC', 'مدیریت استخدام', OrgUnitType::Department);
        $this->createUnit($org, $hrDeputy, 'HR-DEV', 'مدیریت آموزش', OrgUnitType::Department);
    }

    private function createUnit(
        Organization $org,
        ?OrgUnit $parent,
        string $code,
        string $name,
        OrgUnitType $type,
    ): OrgUnit {
        return OrgUnit::create([
            'organization_id' => $org->id,
            'parent_id' => $parent?->id,
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'is_active' => true,
            'activated_at' => now()->toDateString(),
        ]);
    }

    private function createSuperAdmin(): void
    {
        $username = env('SUPER_ADMIN_USERNAME', 'admin');
        $password = env('SUPER_ADMIN_PASSWORD', 'ChangeMe@123456');
        $email = env('SUPER_ADMIN_EMAIL', 'admin@example.com');

        $user = User::firstOrCreate(
            ['username' => $username],
            [
                'email' => $email,
                'first_name' => 'مدیر',
                'last_name' => 'سیستم',
                'display_name' => 'مدیر سیستم',
                'password' => $password, // hashed cast
                'password_changed_at' => now(),
                'status' => UserStatus::Active,
                'is_external' => false,
                'is_system' => true,
                'email_verified_at' => now(),
                'preferred_locale' => 'fa',
                'preferred_calendar' => 'jalali',
                'timezone' => 'Asia/Tehran',
            ]
        );

        if (!$user->hasRole('super-admin')) {
            $user->assignRole('super-admin');
        }

        $this->command?->info("Super admin created: {$username} / {$password}");
        $this->command?->warn('⚠ پسورد را در محیط production حتماً تغییر دهید!');
    }
}
