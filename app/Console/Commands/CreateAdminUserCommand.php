<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use Illuminate\Console\Command;

/**
 * دستور سریع برای ساخت کاربر admin.
 *
 * جایگزین `php artisan make:filament-user` که با User model سفارشی ما سازگار نیست.
 *
 * استفاده:
 *   php artisan mms:create-admin
 *   php artisan mms:create-admin --username=admin --email=admin@co.ir --password=Secret123
 */
class CreateAdminUserCommand extends Command
{
    protected $signature = 'mms:create-admin
        {--username= : نام کاربری}
        {--email= : ایمیل}
        {--password= : رمز عبور}
        {--role=super-admin : نقش (super-admin, system-admin, organization-admin)}';

    protected $description = 'ساخت کاربر admin برای دسترسی اولیه به پنل';

    public function handle(): int
    {
        $username = $this->option('username') ?: $this->ask('نام کاربری', 'admin');
        $email = $this->option('email') ?: $this->ask('ایمیل', 'admin@example.com');
        $password = $this->option('password') ?: $this->ask('رمز عبور (حداقل ۸ کاراکتر)');
        $role = $this->option('role');

        if (empty($password) || strlen($password) < 8) {
            $this->error('رمز عبور نمی‌تواند خالی باشد و باید حداقل ۸ کاراکتر داشته باشد.');
            return self::FAILURE;
        }


        $user = User::updateOrCreate(
            ['username' => $username],
            [
                'email' => $email,
                'first_name' => 'مدیر',
                'last_name' => 'سیستم',
                'display_name' => 'مدیر سیستم',
                'password' => bcrypt($password),
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

        if (!$user->hasRole($role)) {
            $user->assignRole($role);
        }

        $this->info("✓ کاربر ساخته شد:");
        $this->table(
            ['فیلد', 'مقدار'],
            [
                ['نام کاربری', $username],
                ['ایمیل', $email],
                ['نقش', $role],
                ['URL پنل', url('/admin')],
            ]
        );

        if ($password === 'ChangeMe@123456') {
            $this->warn('⚠  رمز عبور پیش‌فرض را تغییر دهید!');
        }

        return self::SUCCESS;
    }
}