<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\UserResource\Pages;

use App\Domains\Identity\Actions\CreateUserAction;
use App\Domains\Identity\DTOs\CreateUserData;
use App\Domains\Identity\Enums\UserStatus;
use App\Filament\Admin\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;

/**
 * صفحه ایجاد کاربر — منطق ایجاد به CreateUserAction واگذار می‌شود.
 * این کلاس فقط داده‌های form را به DTO تبدیل و Action را صدا می‌زند.
 */
class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * Override رفتار پیش‌فرض ایجاد. به‌جای ساخت مستقیم Eloquent model،
     * از CreateUserAction استفاده می‌کنیم تا تمام منطق کسب‌وکار (transaction، event، audit، role assignment)
     * در یک نقطه واحد متمرکز بماند.
     */
    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $roles = $data['roles'] ?? [];

        $dto = new CreateUserData(
            username: $data['username'],
            first_name: $data['first_name'],
            last_name: $data['last_name'],
            email: $data['email'] ?? null,
            national_code: $data['national_code'] ?? null,
            mobile: $data['mobile'] ?? null,
            phone: $data['phone'] ?? null,
            display_name: $data['display_name'] ?? null,
            password: $data['password'] ?? null,
            is_external: $data['is_external'] ?? false,
            mfa_enabled: $data['mfa_enabled'] ?? false,
            ldap_guid: $data['ldap_guid'] ?? null,
            ldap_domain: $data['ldap_domain'] ?? null,
            sso_subject: $data['sso_subject'] ?? null,
            hrs_employee_code: $data['hrs_employee_code'] ?? null,
            status: UserStatus::from($data['status'] ?? 'active'),
            preferred_locale: $data['preferred_locale'] ?? 'fa',
            preferred_calendar: $data['preferred_calendar'] ?? 'jalali',
            timezone: $data['timezone'] ?? 'Asia/Tehran',
            roles: is_array($roles) ? array_map(fn ($r) => is_numeric($r)
                ? \Spatie\Permission\Models\Role::find($r)?->name
                : $r, $roles) : [],
        );

        return app(CreateUserAction::class)->execute($dto);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
