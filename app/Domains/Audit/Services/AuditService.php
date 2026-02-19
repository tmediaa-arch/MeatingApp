<?php

declare(strict_types=1);

namespace App\Domains\Audit\Services;

use App\Domains\Audit\Models\AuditLog;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\UserDelegation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;

/**
 * Class AuditService
 *
 * هسته ثبت تمام audit log ها. این service تنها نقطه ورود به جدول audit_logs است.
 * هیچ کد دیگری نباید مستقیماً AuditLog::create() کند.
 *
 * چرا تک نقطه ورود؟
 * - تضمین یکپارچگی فرمت داده‌ها
 * - تضمین گرفتن IP/UA/User
 * - تضمین severity و category مناسب
 * - امکان افزودن enrichment بعدی (مثل GeoIP) در یک نقطه
 * - امکان روتر برای ELK/Splunk در یک نقطه
 *
 * Correlation ID:
 * یک UUID که در طول یک HTTP request ثابت می‌ماند و به همه audit log های آن request می‌چسبد.
 * این برای trace کردن عملیات chain شده بسیار مهم است.
 */
class AuditService
{
    private ?string $correlationId = null;

    /**
     * ثبت یک audit log
     *
     * @param string $event نام رویداد (created, updated, deleted, signed, ...)
     * @param Model|null $auditable موجودیت مربوطه
     * @param string|null $description توضیح خوانا
     * @param array|null $oldValues مقادیر قدیمی
     * @param array|null $newValues مقادیر جدید
     * @param array|null $context زمینه اضافی
     * @param string $severity سطح: debug|info|notice|warning|critical
     * @param string|null $category دسته (در صورت null، از Model گرفته می‌شود)
     * @param string|null $tag تگ اختیاری
     */
    public function log(
        string $event,
        ?Model $auditable = null,
        ?string $description = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $context = null,
        string $severity = 'info',
        ?string $category = null,
        ?string $tag = null,
    ): AuditLog {
        $user = Auth::user();
        $changedFields = null;

        // محاسبه changed_fields از diff کردن old/new
        if ($oldValues !== null && $newValues !== null) {
            $changedFields = array_keys(array_diff_assoc($newValues, $oldValues));
        } elseif ($newValues !== null && $oldValues === null) {
            $changedFields = array_keys($newValues);
        }

        // تشخیص delegation فعال
        [$onBehalfOfId, $delegationId] = $this->resolveDelegation($user);

        return AuditLog::create([
            'user_id' => $user?->id,
            'user_display_name' => $user?->resolved_display_name,
            'on_behalf_of_user_id' => $onBehalfOfId,
            'delegation_id' => $delegationId,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'event' => $event,
            'action_category' => $category ?? ($auditable ? class_basename($auditable) : null),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'changed_fields' => $changedFields,
            'description' => $description,
            'context' => $context,
            'ip_address' => $this->resolveIpAddress(),
            'user_agent' => $this->resolveUserAgent(),
            'request_method' => Request::method() ?: null,
            'request_url' => $this->resolveRequestUrl(),
            'request_id' => $this->resolveRequestId(),
            'tag' => $tag,
            'correlation_id' => $this->getCorrelationId(),
            'severity' => $severity,
            'performed_at' => now(),
        ]);
    }

    /**
     * ثبت ساده برای ایجاد یک موجودیت
     */
    public function logCreated(Model $model, ?array $context = null): AuditLog
    {
        return $this->log(
            event: 'created',
            auditable: $model,
            newValues: $this->extractAuditableAttributes($model),
            context: $context,
        );
    }

    /**
     * ثبت برای update موجودیت
     */
    public function logUpdated(Model $model, array $original, ?array $context = null): AuditLog
    {
        $changed = $model->getChanges();
        // حذف فیلدهای exclude شده
        $excludes = method_exists($model, 'auditExclude') ? $model->auditExclude() : [];
        foreach ($excludes as $field) {
            unset($changed[$field]);
            unset($original[$field]);
        }

        $oldValues = array_intersect_key($original, $changed);

        return $this->log(
            event: 'updated',
            auditable: $model,
            oldValues: $oldValues,
            newValues: $changed,
            context: $context,
        );
    }

    /**
     * ثبت برای delete موجودیت
     */
    public function logDeleted(Model $model, ?array $context = null): AuditLog
    {
        return $this->log(
            event: 'deleted',
            auditable: $model,
            oldValues: $this->extractAuditableAttributes($model),
            context: $context,
            severity: 'notice',
        );
    }

    public function logSecurityEvent(
        string $event,
        string $description,
        string $severity = 'warning',
        ?array $context = null,
    ): AuditLog {
        return $this->log(
            event: $event,
            description: $description,
            context: $context,
            severity: $severity,
            category: 'security',
            tag: 'security',
        );
    }

    /**
     * ست کردن correlation_id برای یک واحد منطقی (مثلاً یک HTTP request)
     */
    public function setCorrelationId(string $id): void
    {
        $this->correlationId = $id;
    }

    public function getCorrelationId(): string
    {
        if ($this->correlationId === null) {
            $this->correlationId = (string) Str::uuid();
        }
        return $this->correlationId;
    }

    // ------------------------- Helpers ------------------------- //

    private function resolveDelegation(?User $user): array
    {
        if (!$user) {
            return [null, null];
        }

        // در فاز اول، تشخیص delegation از طریق session attribute
        // فاز بعدی: middleware یا context provider
        $delegationId = session('active_delegation_id');
        $onBehalfOfId = session('on_behalf_of_user_id');

        if ($delegationId && $onBehalfOfId) {
            // verify delegation is still valid
            $delegation = UserDelegation::find($delegationId);
            if ($delegation && $delegation->isActive() && $delegation->delegate_user_id === $user->id) {
                return [$onBehalfOfId, $delegationId];
            }
        }

        return [null, null];
    }

    private function resolveIpAddress(): ?string
    {
        try {
            return Request::ip();
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveUserAgent(): ?string
    {
        try {
            return substr(Request::userAgent() ?? '', 0, 500) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveRequestUrl(): ?string
    {
        try {
            return substr(Request::fullUrl(), 0, 500);
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveRequestId(): ?string
    {
        try {
            return Request::header('X-Request-Id');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * استخراج attributeهای قابل ثبت از مدل (با حذف exclude ها)
     */
    private function extractAuditableAttributes(Model $model): array
    {
        $attributes = $model->getAttributes();

        $excludes = method_exists($model, 'auditExclude')
            ? $model->auditExclude()
            : ['password', 'remember_token'];

        foreach ($excludes as $field) {
            unset($attributes[$field]);
        }

        // محدودسازی فقط به فیلدهای فعال (در صورت تعریف auditOnly)
        if (method_exists($model, 'auditOnly')) {
            $only = $model->auditOnly();
            if (!empty($only)) {
                $attributes = array_intersect_key($attributes, array_flip($only));
            }
        }

        return $attributes;
    }
}
