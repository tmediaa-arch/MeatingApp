<?php

declare(strict_types=1);

namespace App\Domains\Reports\Models;

use App\Domains\Files\Models\File;
use App\Domains\Identity\Models\User;
use App\Domains\Organization\Models\Organization;
use App\Domains\Reports\Enums\ReportRunStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ReportRun — رکورد هر اجرای یک گزارش.
 *
 * این مدل دو هدف را پوشش می‌دهد:
 * 1. Audit trail — چه کسی، کی، چه گزارشی، با چه پارامترهایی اجرا کرد
 * 2. Cache — اگر گزارش cacheable است، تا cached_until می‌توان از result_data
 *    به جای اجرای مجدد استفاده کرد (lookup با params_hash)
 */
class ReportRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_id', 'organization_id', 'requested_by_user_id',
        'input_params', 'params_hash',
        'status', 'started_at', 'completed_at', 'duration_ms',
        'result_data', 'row_count',
        'output_file_id', 'output_format',
        'error_message', 'cached_until', 'metadata',
    ];

    protected $casts = [
        'status' => ReportRunStatus::class,
        'input_params' => 'array',
        'result_data' => 'array',
        'metadata' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cached_until' => 'datetime',
    ];

    // ──────── روابط ────────

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function outputFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'output_file_id');
    }

    // ──────── Scopes ────────

    public function scopeCompleted($query)
    {
        return $query->where('status', ReportRunStatus::Completed);
    }

    public function scopeFresh($query, string $paramsHash)
    {
        return $query
            ->where('params_hash', $paramsHash)
            ->where('status', ReportRunStatus::Completed)
            ->whereNotNull('cached_until')
            ->where('cached_until', '>', now());
    }

    // ──────── Helpers ────────

    public function isCacheFresh(): bool
    {
        return $this->cached_until !== null
            && $this->cached_until->isFuture()
            && $this->status === ReportRunStatus::Completed;
    }

    public function markStarted(): void
    {
        $this->forceFill([
            'status' => ReportRunStatus::Running,
            'started_at' => now(),
        ])->save();
    }

    public function markCompleted(array $resultData, int $rowCount, ?\DateTimeInterface $cacheUntil = null): void
    {
        $startedAt = $this->started_at ?? $this->created_at;
        $this->forceFill([
            'status' => ReportRunStatus::Completed,
            'completed_at' => now(),
            'duration_ms' => (int) ($startedAt ? now()->diffInMilliseconds($startedAt) : 0),
            'result_data' => $resultData,
            'row_count' => $rowCount,
            'cached_until' => $cacheUntil,
            'error_message' => null,
        ])->save();
    }

    public function markFailed(string $message): void
    {
        $startedAt = $this->started_at ?? $this->created_at;
        $this->forceFill([
            'status' => ReportRunStatus::Failed,
            'completed_at' => now(),
            'duration_ms' => (int) ($startedAt ? now()->diffInMilliseconds($startedAt) : 0),
            'error_message' => substr($message, 0, 5000),
        ])->save();
    }
}
