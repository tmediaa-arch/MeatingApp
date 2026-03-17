<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Organization\Models\Organization;
use App\Domains\Workflow\Enums\ProcessInstanceStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $instance_uuid
 * @property int $process_definition_id
 * @property string $process_key
 * @property int $process_version
 * @property int $organization_id
 * @property string|null $business_key
 * @property ProcessInstanceStatus $status
 * @property \Carbon\Carbon|null $started_at
 * @property \Carbon\Carbon|null $completed_at
 * @property \Carbon\Carbon|null $sla_due_at
 * @property string $priority
 */
class ProcessInstance extends Model
{
    use HasFactory, SoftDeletes;

    protected static function newFactory()
    {
        return \Database\Factories\ProcessInstanceFactory::new();
    }

    protected $fillable = [
        'instance_uuid',
        'process_definition_id',
        'process_key',
        'process_version',
        'organization_id',
        'business_key',
        'subject_type',
        'subject_id',
        'status',
        'started_at',
        'completed_at',
        'cancelled_at',
        'suspended_at',
        'end_reason',
        'failure_reason',
        'priority',
        'sla_due_at',
        'starter_user_id',
        'start_variables',
        'context',
    ];

    protected $casts = [
        'status' => ProcessInstanceStatus::class,
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'suspended_at' => 'datetime',
        'sla_due_at' => 'datetime',
        'start_variables' => 'array',
        'context' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $instance) {
            if (empty($instance->instance_uuid)) {
                $instance->instance_uuid = (string) Str::uuid();
            }
        });
    }

    // ──────── Relations ────────

    public function definition(): BelongsTo
    {
        return $this->belongsTo(ProcessDefinition::class, 'process_definition_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(ProcessToken::class, 'instance_id');
    }

    public function activeTokens(): HasMany
    {
        return $this->hasMany(ProcessToken::class, 'instance_id')
            ->whereIn('status', ['active', 'waiting']);
    }

    public function variables(): HasMany
    {
        return $this->hasMany(ProcessVariable::class, 'instance_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(ProcessHistory::class, 'instance_id');
    }

    public function userTasks(): HasMany
    {
        return $this->hasMany(UserTask::class, 'instance_id');
    }

    public function activeUserTasks(): HasMany
    {
        return $this->hasMany(UserTask::class, 'instance_id')
            ->whereIn('status', ['created', 'assigned', 'claimed']);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(ProcessIncident::class, 'instance_id');
    }

    public function openIncidents(): HasMany
    {
        return $this->hasMany(ProcessIncident::class, 'instance_id')
            ->where('status', 'open');
    }

    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'starter_user_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    // ──────── Scopes ────────

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereIn('status', [
            ProcessInstanceStatus::Pending->value,
            ProcessInstanceStatus::Running->value,
        ]);
    }

    public function scopeSlaBreached(Builder $q): Builder
    {
        return $q->active()
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<', now());
    }

    public function scopeForKey(Builder $q, string $key): Builder
    {
        return $q->where('process_key', $key);
    }

    // ──────── Helpers ────────

    public function getVariable(string $name): mixed
    {
        $var = $this->variables()->where('name', $name)->first();
        return $var?->getValue();
    }

    public function getAllVariables(): array
    {
        return $this->variables()
            ->get()
            ->keyBy('name')
            ->map(fn ($v) => $v->getValue())
            ->toArray();
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function isCompleted(): bool
    {
        return $this->status === ProcessInstanceStatus::Completed;
    }

    public function isFailed(): bool
    {
        return $this->status === ProcessInstanceStatus::Failed;
    }
}
