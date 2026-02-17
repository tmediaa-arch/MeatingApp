<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Identity\DTOs\CreateUserData;
use App\Domains\Identity\Events\UserCreated;
use App\Domains\Identity\Exceptions\IdentityException;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Class CreateUserAction
 *
 * منطق کسب‌وکار ایجاد کاربر.
 *
 * چرا Action و نه روش‌های دیگر؟
 * - Single Responsibility: یک کار را به‌خوبی انجام می‌دهد
 * - قابل تست: می‌توان به‌سادگی mock کرد
 * - قابل استفاده مجدد: از Controller، Filament، Console، Job
 * - تراکنش‌پذیر: همه عملیات داخل یک transaction
 * - Event-driven: پس از موفقیت event می‌فرستد
 *
 * چرا منطق رمزنگاری password اینجاست؟
 * - حساس‌ترین لحظه فقط ایجاد است
 * - Hash::make در یک نقطه واحد متمرکز است
 * - اگر کاربر LDAP باشد، password می‌تواند random باشد
 *
 * نکته مهم: validation در DTO انجام می‌شود، نه اینجا.
 * این Action فرض می‌کند data معتبر است.
 */
class CreateUserAction
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {
    }

    /**
     * @throws IdentityException
     */
    public function execute(CreateUserData $data): User
    {
        return DB::transaction(function () use ($data) {
            // 1. تولید/Hash رمز عبور
            $password = $this->resolvePassword($data);

            // 2. ایجاد کاربر
            $user = User::create([
                'username' => $data->username,
                'email' => $data->email,
                'national_code' => $data->national_code,
                'first_name' => $data->first_name,
                'last_name' => $data->last_name,
                'display_name' => $data->display_name ?: trim($data->first_name . ' ' . $data->last_name),
                'mobile' => $data->mobile,
                'phone' => $data->phone,
                'password' => $password, // Hash توسط cast 'hashed' انجام می‌شود
                'password_changed_at' => now(),
                'status' => $data->status,
                'is_external' => $data->is_external,
                'is_system' => $data->is_system,
                'mfa_enabled' => $data->mfa_enabled,
                'ldap_guid' => $data->ldap_guid,
                'ldap_domain' => $data->ldap_domain,
                'sso_subject' => $data->sso_subject,
                'hrs_employee_code' => $data->hrs_employee_code,
                'employee_id' => $data->employee_id,
                'preferred_locale' => $data->preferred_locale,
                'preferred_calendar' => $data->preferred_calendar,
                'timezone' => $data->timezone,
            ]);

            // 3. انتساب نقش‌ها
            if (!empty($data->roles)) {
                $user->syncRoles($data->roles);
            }

            // 4. ثبت در audit log
            $this->auditService->log(
                event: 'user_created',
                auditable: $user,
                description: "کاربر '{$user->username}' ایجاد شد",
                context: [
                    'roles' => $data->roles,
                    'is_external' => $data->is_external,
                    'has_password' => !empty($data->password),
                ],
                severity: 'notice',
            );

            // 5. ارسال event
            event(new UserCreated($user));

            return $user->fresh(['roles']);
        });
    }

    private function resolvePassword(CreateUserData $data): string
    {
        // اگر کاربر LDAP/SSO است، رمز موقت random
        if ($data->ldap_guid !== null || $data->sso_subject !== null) {
            return Str::random(32);
        }

        if (empty($data->password)) {
            throw new IdentityException('Password is required for non-LDAP/SSO users');
        }

        return $data->password;
    }
}
