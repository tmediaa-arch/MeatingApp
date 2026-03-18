<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Organization\Models\Employee;
use App\Domains\Tasks\Models\Task;
use App\Domains\Workflow\Enums\UserTaskStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * UserTask تولید شده در زمان رسیدن یک token به یک عنصر userTask.
 *
 * @property int $id
 * @property int $instance_id
 * @property int $token_id
 * @property string $element_id
 * @property string $name
 * @property string|null $description
 * @property int|null $assignee_user_id
 * @property UserTaskStatus $status
 * @property string $priority
 * @property \Carbon\Carbon|null $due_at
 * @property array|null $form_schema
 * @property array|null $form_data
 * @property string|null $outcome
 */
class UserTask extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return \Database\Factories\UserTaskFactory::new();
    }

    protected $table = 'process_user_tasks';

    protected $fillable = [
        'instance_id',
        'token_id',
        'element_id',
        'name',
        'description',
        'assignee_user_id',
        'assignee_employee_id',
        'candidate_user_ids',
        'candidate_role_names',
        'status',
        'priority',
        'due_at',
        'follow_up_at',
        'form_schema',
        'form_data',
        'outcome',
        'outcome_comment',
        'task_id',
        'claimed_at',
        'completed_at',
        'completed_by_user_id',
    ];

    protected $casts = [
        'candidate_user_ids' => 'array',
        'candidate_role_names' => 'array',
        'form_schema' => 'array',
        'form_data' => 'array',
        'status' => UserTaskStatus::class,
        'due_at' => 'datetime',
        'follow_up_at' => 'datetime',
        'claimed_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // ──────── Relations ────────

    public function instance(): BelongsTo
    {
        return $this->belongsTo(ProcessInstance::class, 'instance_id');
    }

    public function token(): BelongsTo
    {
        return $this->belongsTo(ProcessToken::class, 'token_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_user_id');
    }

    public function assigneeEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assignee_employee_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }

    public function relatedTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    // ──────── Scopes ────────

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereIn('status', [
            UserTaskStatus::Created->value,
            UserTaskStatus::Assigned->value,
            UserTaskStatus::Claimed->value,
        ]);
    }

    public function scopeForUser(Builder $q, User $user): Builder
    {
        return $q->where(function ($qq) use ($user) {
            $qq->where('assignee_user_id', $user->id)
               ->orWhereJsonContains('candidate_user_ids', $user->id);
        });
    }

    public function scopeOverdue(Builder $q): Builder
    {
        return $q->open()
            ->whereNotNull('due_at')
            ->where('due_at', '<', now());
    }

    // ──────── Helpers ────────

    public function canBeClaimedBy(User $user): bool
    {
        if (!$this->status->isOpen()) return false;

        // اگر مستقیم assigned شده
        if ($this->assignee_user_id === $user->id) return true;

        // اگر در candidate users است
        if (in_array($user->id, $this->candidate_user_ids ?? [], true)) return true;

        // اگر در candidate roles است و کاربر آن نقش را دارد
        foreach ($this->candidate_role_names ?? [] as $roleName) {
            if (method_exists($user, 'hasRole') && $user->hasRole($roleName)) return true;
        }

        return false;
    }

    public function canBeCompletedBy(User $user): bool
    {
        if ($this->status === UserTaskStatus::Completed) return false;
        if ($this->status === UserTaskStatus::Claimed && $this->assignee_user_id !== $user->id) return false;
        return $this->canBeClaimedBy($user);
    }

    public function isOverdue(): bool
    {
        return $this->due_at && $this->due_at->isPast() && $this->status->isOpen();
    }
}
