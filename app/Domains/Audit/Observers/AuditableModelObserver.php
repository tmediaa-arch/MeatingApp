<?php

declare(strict_types=1);

namespace App\Domains\Audit\Observers;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Shared\Concerns\HasAuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Class AuditableModelObserver
 *
 * هر مدلی که trait HasAuditLog را استفاده کند می‌تواند به این Observer
 * متصل شود تا به‌صورت خودکار تمام تغییرات آن audit شود.
 *
 * در DomainServiceProvider به مدل‌ها attach می‌شود.
 */
class AuditableModelObserver
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {
    }

    public function created(Model $model): void
    {
        if (!$this->shouldAudit($model)) {
            return;
        }

        $this->auditService->logCreated($model);
    }

    public function updated(Model $model): void
    {
        if (!$this->shouldAudit($model)) {
            return;
        }

        // اگر فقط فیلدهای exclude شده تغییر کردند، audit نکن
        $changes = $model->getChanges();
        $excludes = method_exists($model, 'auditExclude') ? $model->auditExclude() : [];
        $meaningfulChanges = array_diff(array_keys($changes), $excludes);

        if (empty($meaningfulChanges)) {
            return;
        }

        $this->auditService->logUpdated($model, $model->getOriginal());
    }

    public function deleted(Model $model): void
    {
        if (!$this->shouldAudit($model)) {
            return;
        }

        $this->auditService->logDeleted($model);
    }

    public function restored(Model $model): void
    {
        if (!$this->shouldAudit($model)) {
            return;
        }

        $this->auditService->log(
            event: 'restored',
            auditable: $model,
            description: 'بازیابی از حالت حذف نرم',
            severity: 'notice',
        );
    }

    private function shouldAudit(Model $model): bool
    {
        return in_array(HasAuditLog::class, class_uses_recursive($model), true);
    }
}
