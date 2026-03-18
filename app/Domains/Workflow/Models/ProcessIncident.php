<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Models;

use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * یک Incident: خطای runtime نیازمند مداخله انسانی.
 *
 * @property int $id
 * @property int $instance_id
 * @property int|null $token_id
 * @property string $incident_type
 * @property string|null $element_id
 * @property string $message
 * @property string $status
 */
class ProcessIncident extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return \Database\Factories\ProcessIncidentFactory::new();
    }

    protected $table = 'process_incidents';

    protected $fillable = [
        'instance_id',
        'token_id',
        'incident_type',
        'element_id',
        'message',
        'stack_trace',
        'context',
        'status',
        'resolved_by_user_id',
        'resolved_at',
        'resolution_note',
    ];

    protected $casts = [
        'context' => 'array',
        'resolved_at' => 'datetime',
    ];

    public function instance(): BelongsTo
    {
        return $this->belongsTo(ProcessInstance::class, 'instance_id');
    }

    public function token(): BelongsTo
    {
        return $this->belongsTo(ProcessToken::class, 'token_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->where('status', 'open');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
