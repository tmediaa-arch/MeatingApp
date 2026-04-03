<?php

declare(strict_types=1);

namespace App\Domains\Reports\Models;

use App\Domains\Audit\Concerns\HasAuditLog;
use App\Domains\Identity\Models\User;
use App\Domains\Organization\Models\Organization;
use Cron\CronExpression;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * ReportSchedule — زمان‌بندی خودکار اجرا و ارسال گزارش.
 *
 * هر schedule یک cron expression دارد. سرویس RunDueReportSchedulesJob
 * هر دقیقه چک می‌کند کدام schedule ها به اجرا نزدیک‌اند.
 */
class ReportSchedule extends Model
{
    use HasFactory, HasAuditLog, SoftDeletes;

    protected $fillable = [
        'report_id', 'organization_id', 'created_by_user_id',
        'name', 'cron_expression', 'input_params', 'output_format',
        'recipient_user_ids', 'recipient_emails', 'delivery_method',
        'is_active', 'last_run_at', 'next_run_at',
    ];

    protected $casts = [
        'input_params' => 'array',
        'recipient_user_ids' => 'array',
        'recipient_emails' => 'array',
        'is_active' => 'boolean',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDue($query)
    {
        return $query
            ->where('is_active', true)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now());
    }

    /**
     * محاسبه next_run_at بعدی بر اساس cron expression
     */
    public function calculateNextRun(?\DateTimeInterface $from = null): ?\DateTimeImmutable
    {
        try {
            $cron = new CronExpression($this->cron_expression);
            $next = $cron->getNextRunDate($from ?? new \DateTimeImmutable());
            return \DateTimeImmutable::createFromMutable($next);
        } catch (\Throwable $e) {
            // expression نامعتبر
            return null;
        }
    }

    public function updateNextRun(): void
    {
        $next = $this->calculateNextRun();
        $this->forceFill(['next_run_at' => $next])->save();
    }
}
