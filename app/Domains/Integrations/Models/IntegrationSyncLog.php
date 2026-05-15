<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Models;

use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * IntegrationSyncLog — لاگ هر اجرای sync یک provider.
 */
class IntegrationSyncLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider_id',
        'triggered_by_user_id',
        'sync_type',
        'direction',
        'status',
        'started_at',
        'completed_at',
        'duration_ms',
        'records_processed',
        'records_created',
        'records_updated',
        'records_skipped',
        'records_failed',
        'error_summary',
        'full_log',
        'metadata',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'duration_ms' => 'integer',
        'records_processed' => 'integer',
        'records_created' => 'integer',
        'records_updated' => 'integer',
        'records_skipped' => 'integer',
        'records_failed' => 'integer',
        'error_summary' => 'array',
        'metadata' => 'array',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(IntegrationProvider::class, 'provider_id');
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }
}
