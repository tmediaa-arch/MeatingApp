<?php

declare(strict_types=1);

namespace App\Domains\Exports\Models;

use App\Domains\Exports\Enums\ExportStatus;
use App\Domains\Exports\Enums\ExportType;
use App\Domains\Files\Models\File;
use App\Domains\Identity\Models\User;
use App\Domains\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExportJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'requested_by_user_id',
        'export_type', 'format',
        'input_params', 'label',
        'status', 'started_at', 'completed_at', 'duration_ms', 'row_count',
        'output_file_id', 'expires_at',
        'error_message', 'metadata',
    ];

    protected $casts = [
        'export_type' => ExportType::class,
        'status' => ExportStatus::class,
        'input_params' => 'array',
        'metadata' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

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

    public function scopeExpired($query)
    {
        return $query->whereNotNull('expires_at')->where('expires_at', '<', now());
    }

    public function scopeActive($query)
    {
        return $query->where('status', ExportStatus::Completed)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function markStarted(): void
    {
        $this->forceFill([
            'status' => ExportStatus::Processing,
            'started_at' => now(),
        ])->save();
    }

    public function markCompleted(File $outputFile, int $rowCount = 0): void
    {
        $startedAt = $this->started_at ?? $this->created_at;
        $this->forceFill([
            'status' => ExportStatus::Completed,
            'completed_at' => now(),
            'duration_ms' => (int) ($startedAt ? now()->diffInMilliseconds($startedAt) : 0),
            'output_file_id' => $outputFile->id,
            'row_count' => $rowCount,
            'expires_at' => $this->expires_at ?? now()->addDays(7),
        ])->save();
    }

    public function markFailed(string $reason): void
    {
        $startedAt = $this->started_at ?? $this->created_at;
        $this->forceFill([
            'status' => ExportStatus::Failed,
            'completed_at' => now(),
            'duration_ms' => (int) ($startedAt ? now()->diffInMilliseconds($startedAt) : 0),
            'error_message' => substr($reason, 0, 5000),
        ])->save();
    }
}
