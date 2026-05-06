<?php

declare(strict_types=1);

namespace App\Domains\Shared\Concerns;

use App\Domains\Audit\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;

/**
 * Trait HasAuditLog
 *
 * هر مدل که audit log می‌خواهد، این trait را use می‌کند.
 * بقیه کار توسط Observer انجام می‌شود.
 *
 * نکته: این trait فقط relationship را تعریف می‌کند.
 * Observer مسئول ثبت log است.
 */
trait HasAuditLog
{
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    /**
     * فیلدهایی که نباید در audit log ثبت شوند (مثل password)
     */
    public function auditExclude(): array
    {
        return property_exists($this, 'auditExclude')
            ? $this->auditExclude
            : ['password', 'remember_token', 'mfa_secret', 'mfa_recovery_codes'];
    }

    /**
     * فیلدهایی که فقط اگر تغییر کنند audit ثبت می‌شود.
     * در صورت خالی بودن، همه فیلدها (به جز exclude) ثبت می‌شوند.
     */
    public function auditOnly(): array
    {
        return property_exists($this, 'auditOnly')
            ? $this->auditOnly
            : [];
    }

    /**
     * دسته‌بندی audit برای گزارش‌گیری
     */
    public function auditCategory(): string
    {
        return property_exists($this, 'auditCategory')
            ? $this->auditCategory
            : class_basename($this);
    }
}
