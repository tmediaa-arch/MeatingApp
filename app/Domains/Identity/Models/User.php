<?php

declare(strict_types=1);

namespace App\Domains\Identity\Models;

use App\Domains\Audit\Models\LoginLog;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Organization\Models\Employee;
use App\Domains\Shared\Concerns\HasAuditLog;
use App\Domains\Shared\Enums\ConfidentialityLevel;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * Class User
 *
 * هسته احراز هویت سامانه.
 *
 * تمایز مهم با Employee:
 * - User موجودیتی است که می‌تواند login کند
 * - Employee موجودیت سازمانی است که در ساختار سازمان جای دارد
 * - یک Employee حتماً یک User دارد (برای ورود)
 * - یک User می‌تواند Employee نداشته باشد (مهمان بیرونی)
 *
 * نکات امنیتی:
 * - mfa_secret و mfa_recovery_codes حتماً encrypted (در $casts)
 * - password توسط Hash::make() ست می‌شود (در Action ها، نه اینجا)
 * - hidden از API exposed نمی‌شود
 *
 * @property int $id
 * @property string $username
 * @property string|null $email
 * @property string|null $national_code
 * @property string $first_name
 * @property string $last_name
 * @property string|null $display_name
 * @property UserStatus $status
 * @property bool $is_external
 * @property bool $is_system
 * @property bool $mfa_enabled
 * @property \Carbon\Carbon|null $last_login_at
 * @property int|null $employee_id
 */
class User extends Authenticatable implements FilamentUser, HasName, MustVerifyEmail
{
    use HasApiTokens;
    use HasAuditLog;
    use HasFactory;
    use HasRoles;
    use Notifiable;
    use SoftDeletes;

    protected $guard_name = 'web';

    protected $fillable = [
        'username',
        'email',
        'national_code',
        'first_name',
        'last_name',
        'display_name',
        'mobile',
        'phone',
        'password',
        'avatar_path',
        'status',
        'is_external',
        'is_system',
        'mfa_enabled',
        'mfa_secret',
        'mfa_recovery_codes',
        'ldap_guid',
        'ldap_domain',
        'sso_subject',
        'hrs_employee_code',
        'employee_id',
        'preferred_locale',
        'preferred_calendar',
        'timezone',
        'notification_preferences',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'mfa_secret',
        'mfa_recovery_codes',
    ];

    protected $auditExclude = [
        'password',
        'remember_token',
        'mfa_secret',
        'mfa_recovery_codes',
        'last_login_at',
        'last_login_ip',
        'last_login_user_agent',
        'failed_login_attempts',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'mobile_verified_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'locked_until' => 'datetime',
            'password' => 'hashed',
            'mfa_secret' => 'encrypted',
            'mfa_recovery_codes' => 'encrypted:array',
            'notification_preferences' => 'array',
            'is_external' => 'boolean',
            'is_system' => 'boolean',
            'mfa_enabled' => 'boolean',
            'status' => UserStatus::class,
        ];
    }

    // ------------------------- Relationships ------------------------- //

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function loginLogs(): HasMany
    {
        return $this->hasMany(LoginLog::class);
    }

    public function delegationsGiven(): HasMany
    {
        return $this->hasMany(UserDelegation::class, 'delegator_user_id');
    }

    public function delegationsReceived(): HasMany
    {
        return $this->hasMany(UserDelegation::class, 'delegate_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ------------------------- Accessors ------------------------- //

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function getResolvedDisplayNameAttribute(): string
    {
        return $this->display_name ?: $this->full_name;
    }

    public function getInitialsAttribute(): string
    {
        $first = mb_substr($this->first_name, 0, 1);
        $last = mb_substr($this->last_name, 0, 1);
        return $first . $last;
    }

    // ------------------------- Scopes ------------------------- //

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', UserStatus::Active->value);
    }

    public function scopeInternal(Builder $query): Builder
    {
        return $query->where('is_external', false);
    }

    public function scopeExternal(Builder $query): Builder
    {
        return $query->where('is_external', true);
    }

    public function scopeLocked(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->where('status', UserStatus::Locked->value)
              ->orWhere(function ($q2) {
                  $q2->whereNotNull('locked_until')
                     ->where('locked_until', '>', now());
              });
        });
    }

    // ------------------------- Business Logic Helpers ------------------------- //

    /**
     * آیا کاربر در حال حاضر قفل است؟
     */
    public function isLocked(): bool
    {
        if ($this->status === UserStatus::Locked) {
            return true;
        }

        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    /**
     * آیا کاربر می‌تواند login کند؟ (وضعیت + قفل + سایر شرایط)
     */
    public function canLogin(): bool
    {
        return $this->status->canLogin() && !$this->isLocked() && !$this->trashed();
    }

    /**
     * سطح محرمانگی مجاز برای این کاربر
     * بر اساس employee.clearance_level یا default
     */
    public function clearanceLevel(): ConfidentialityLevel
    {
        if ($this->employee?->clearance_level) {
            return ConfidentialityLevel::from($this->employee->clearance_level);
        }

        return $this->is_external
            ? ConfidentialityLevel::Public
            : ConfidentialityLevel::Internal;
    }

    /**
     * آیا کاربر در حال حاضر تفویض فعال دریافت کرده؟
     */
    public function hasActiveDelegationReceived(): bool
    {
        return $this->delegationsReceived()
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->exists();
    }

    /**
     * تنظیمات شخصی داشبورد کاربر — Phase 6
     */
    public function dashboardPreferences(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Domains\Dashboards\Models\UserDashboardPreference::class);
    }

    /**
     * نگاشت LDAP این کاربر — Phase 6
     */
    public function ldapMappings(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Domains\Integrations\Models\LdapUserMapping::class);
    }

    /**
     * Session های SSO فعال این کاربر — Phase 6
     */
    public function ssoSessions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Domains\Integrations\Models\SsoSession::class);
    }

    // ------------------------- Filament Contracts ------------------------- //

    public function canAccessPanel(Panel $panel): bool
    {
        // بررسی اولیه: کاربر باید active باشد
        if (!$this->canLogin()) {
            return false;
        }

        // super-admin همیشه دسترسی دارد
        if ($this->hasRole('super-admin')) {
            return true;
        }

        return match ($panel->getId()) {
            // مدیران سامانه، کارمندان و هر کاربری که نقشی داشته باشد
            // (از جمله کاربران دعوت‌شده با نقش invitee) به پنل دسترسی دارند.
            'admin' => $this->hasAnyRole(['system-admin', 'organization-admin'])
                || $this->employee !== null
                || $this->roles()->exists(),
            default => true,
        };
    }

    public function getFilamentName(): string
    {
        return $this->resolved_display_name;
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->avatar_path
            ? asset('storage/' . $this->avatar_path)
            : null;
    }
}