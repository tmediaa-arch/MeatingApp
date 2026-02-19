<?php

declare(strict_types=1);

namespace App\Domains\Audit\Concerns;

/**
 * Alias — trait اصلی در Shared\Concerns\HasAuditLog است.
 * این فایل برای سازگاری با مدل‌هایی که از مسیر Audit\Concerns import می‌کنند.
 */
trait HasAuditLog
{
    use \App\Domains\Shared\Concerns\HasAuditLog;
}
