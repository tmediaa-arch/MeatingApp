<?php

declare(strict_types=1);

namespace App\Domains\Tasks\Models;

use App\Domains\Audit\Concerns\HasAuditLog;
use App\Domains\Audit\Concerns\TracksUserChanges;
use App\Domains\Identity\Models\User;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Organization\Models\Employee;
use App\Domains\Organization\Models\OrgUnit;
use App\Domains\Organization\Models\Organization;
use App\Domains\Resolutions\Models\Resolution;
use App\Domains\Shared\Enums\ConfidentialityLevel;
use App\Domains\Tasks\Enums\TaskPriority;
use App\Domains\Tasks\Enums\TaskStatus;
use App\Domains\Tasks\Enums\TaskType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use HasFactory, HasAuditLog, TracksUserChanges, SoftDeletes;

    protected $fillable = [
        'organization_id', 'task_number',
        'resolution_id', 'meeting_id', 'parent_task_id',
        'title', 'description', 'type', 'priority', 'status',
        'assignee_employee_id', 'assignee_user_id', 'assignee_org_unit_id',
        'supervisor_employee_id', 'approver_employee_id',
        'assigned_at', 'due_date', 'started_at', 'submitted_at',
        'completed_at', 'cancelled_at',
        'estimated_hours', 'actual_hours', 'progress_percent',
        'is_overdue', 'escalation_level', 'last_escalated_at',
        'result_summary', 'completion_quality',
        'tags', 'metadata', 'confidentiality_level', 'creator_user_id',
    ];

    protected $casts = [
        'status' => TaskStatus::class,
        'priority' => TaskPriority::class,
        'type' => TaskType::class,
        'confidentiality_level' => ConfidentialityLevel::class,
        'tags' => 'array',
        'metadata' => 'array',
        'due_date' => 'date',
        'assigned_at' => 'datetime',
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'last_escalated_at' => 'datetime',
        'is_overdue' => 'boolean',
    ];

    // ──────── روابط ────────

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function resolution(): BelongsTo
    {
        return $this->belongsTo(Resolution::class);
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_task_id');
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(self::class, 'parent_task_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assignee_employee_id');
    }

    public function assigneeUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_user_id');
    }

    public function assigneeOrgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'assignee_org_unit_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'supervisor_employee_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approver_employee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_user_id');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(TaskUpdate::class)->orderBy('occurred_at', 'desc');
    }

    public function extensions(): HasMany
    {
        return $this->hasMany(TaskExtension::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class);
    }

    // ──────── Scopes ────────

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            TaskStatus::Completed->value,
            TaskStatus::Cancelled->value,
        ]);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->toDateString())
            ->whereNotIn('status', [
                TaskStatus::Completed->value,
                TaskStatus::Cancelled->value,
            ]);
    }

    public function scopeForAssignee(Builder $query, int $employeeId): Builder
    {
        return $query->where('assignee_employee_id', $employeeId);
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where(function ($q) use ($user) {
            $q->where('creator_user_id', $user->id)
              ->orWhere('assignee_user_id', $user->id);

            if ($user->employee_id) {
                $q->orWhere('assignee_employee_id', $user->employee_id)
                  ->orWhere('supervisor_employee_id', $user->employee_id)
                  ->orWhere('approver_employee_id', $user->employee_id);
            }
        });
    }

    public function scopeByPriority(Builder $query, TaskPriority $priority): Builder
    {
        return $query->where('priority', $priority->value);
    }

    public function scopeDueWithinDays(Builder $query, int $days): Builder
    {
        return $query
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [
                now()->toDateString(),
                now()->addDays($days)->toDateString(),
            ]);
    }

    // ──────── Helpers ────────

    public function isOverdue(): bool
    {
        if (!$this->due_date) return false;
        if ($this->status->isTerminal()) return false;
        return $this->due_date->isPast();
    }

    public function daysUntilDue(): ?int
    {
        if (!$this->due_date) return null;
        return now()->startOfDay()->diffInDays($this->due_date, false);
    }

    public function canBeViewedBy(User $user): bool
    {
        if (!$user->clearanceLevel()->canAccess($this->confidentiality_level)) {
            return false;
        }

        if ($user->hasPermissionTo('task.view_all')) return true;

        // مرتبط بودن کاربر با task
        if ($this->creator_user_id === $user->id) return true;
        if ($this->assignee_user_id === $user->id) return true;
        if ($user->employee_id && (
            $this->assignee_employee_id === $user->employee_id
            || $this->supervisor_employee_id === $user->employee_id
            || $this->approver_employee_id === $user->employee_id
        )) return true;

        return false;
    }

    public function canBeUpdatedBy(User $user): bool
    {
        if (!$this->status->isOpen()) return false;

        if ($user->hasRole('super-admin')) return true;

        // مجری می‌تواند progress/status را به‌روز کند
        if ($user->employee_id === $this->assignee_employee_id) return true;
        if ($this->assignee_user_id === $user->id) return true;

        return false;
    }
}
