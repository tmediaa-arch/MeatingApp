<?php

declare(strict_types=1);

namespace App\Domains\Identity\DTOs;

use App\Domains\Identity\Enums\UserStatus;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * DTO برای ایجاد کاربر جدید
 *
 * استفاده از spatie/laravel-data به ما type-safety می‌دهد
 * و در عین حال validation و serialization رایگان است.
 */
class CreateUserData extends Data
{
    public function __construct(
        public string $username,
        public string $first_name,
        public string $last_name,
        public ?string $email = null,
        public ?string $national_code = null,
        public ?string $mobile = null,
        public ?string $phone = null,
        public ?string $display_name = null,
        public ?string $password = null,
        public bool $is_external = false,
        public bool $is_system = false,
        public bool $mfa_enabled = false,
        public ?int $employee_id = null,
        public ?string $ldap_guid = null,
        public ?string $ldap_domain = null,
        public ?string $sso_subject = null,
        public ?string $hrs_employee_code = null,
        public UserStatus $status = UserStatus::Active,
        public string $preferred_locale = 'fa',
        public string $preferred_calendar = 'jalali',
        public string $timezone = 'Asia/Tehran',
        public array $roles = [],
    ) {
    }

    public static function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:100', 'unique:users,username'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:200', 'unique:users,email'],
            'national_code' => ['nullable', 'string', 'size:10', 'unique:users,national_code'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'password' => ['nullable', 'string', 'min:10'],
            'is_external' => ['boolean'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'preferred_locale' => ['string', 'in:fa,en,ar'],
            'preferred_calendar' => ['string', 'in:jalali,gregorian,hijri'],
            'timezone' => ['string', 'max:50'],
            'roles' => ['array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ];
    }
}
