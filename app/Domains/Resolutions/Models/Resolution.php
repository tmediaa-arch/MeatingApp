<?php

declare(strict_types=1);

namespace App\Domains\Resolutions\Models;

use App\Domains\Audit\Concerns\HasAuditLog;
use App\Domains\Audit\Concerns\TracksUserChanges;
use App\Domains\Identity\Models\User;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Models\MeetingAgendaItem;
use App\Domains\Minutes\Models\Minute;
use App\Domains\Organization\Models\Organization;
use App\Domains\Resolutions\Enums\ResolutionStatus;
use App\Domains\Resolutions\Enums\ResolutionType;
use App\Domains\Tasks\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * مصوبه (Resolution) — تصمیم رسمی برخاسته از جلسه.
 *
 * هر مصوبه به یک minute متصل است و می‌تواند چند Task تولید کند.
 */
class Resolution extends Model
{
    use HasFactory, HasAuditLog, TracksUserChanges, SoftDeletes;

    protected $fillable = [
        'minute_id', 'meeting_id', 'organization_id', 'resolution_number',
        'agenda_item_id', 'title', 'content', 'rationale',
        'type', 'priority', 'status',
        'requires_voting', 'voting_type', 'quorum_required', 'majority_threshold_percent',
        'voting_opened_at', 'voting_closed_at',
        'votes_for', 'votes_against', 'votes_abstain', 'voters_total',
        'due_date', 'approved_at', 'approved_by_user_id',
        'completed_at', 'cancelled_at', 'cancellation_reason',
        'tags', 'metadata', 'creator_user_id',
    ];

    protected $casts = [
        'type' => ResolutionType::class,
        'status' => ResolutionStatus::class,
        'requires_voting' => 'boolean',
        'tags' => 'array',
        'metadata' => 'array',
        'due_date' => 'date',
        'voting_opened_at' => 'datetime',
        'voting_closed_at' => 'datetime',
        'approved_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    // ──────── روابط ────────

    public function minute(): BelongsTo
    {
        return $this->belongsTo(Minute::class);
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function agendaItem(): BelongsTo
    {
        return $this->belongsTo(MeetingAgendaItem::class, 'agenda_item_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(ResolutionVote::class);
    }

    public function assignees(): HasMany
    {
        return $this->hasMany(ResolutionAssignee::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    // ──────── Scopes ────────

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [
            ResolutionStatus::Completed->value,
            ResolutionStatus::Cancelled->value,
            ResolutionStatus::Failed->value,
        ]);
    }

    public function scopeOverdue($query)
    {
        return $query
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->toDateString())
            ->whereNotIn('status', [
                ResolutionStatus::Completed->value,
                ResolutionStatus::Cancelled->value,
                ResolutionStatus::Failed->value,
            ]);
    }

    public function scopeForOrganization($query, int $orgId)
    {
        return $query->where('organization_id', $orgId);
    }

    // ──────── Voting Helpers ────────

    public function isVotingOpen(): bool
    {
        if (!$this->requires_voting) return false;
        if (!$this->voting_opened_at) return false;
        if ($this->voting_closed_at && $this->voting_closed_at < now()) return false;
        return true;
    }

    public function hasReachedQuorum(): bool
    {
        if (!$this->quorum_required) return true;
        return $this->voters_total >= $this->quorum_required;
    }

    public function isPassing(): bool
    {
        if (!$this->hasReachedQuorum()) return false;

        $threshold = $this->majority_threshold_percent ?? 50;
        $valid = $this->votes_for + $this->votes_against;
        if ($valid === 0) return false;

        $percentFor = ($this->votes_for / $valid) * 100;
        return $percentFor > $threshold;
    }

    public function getVotingProgressAttribute(): array
    {
        $valid = $this->votes_for + $this->votes_against;
        $percentFor = $valid > 0 ? round(($this->votes_for / $valid) * 100, 1) : 0;

        return [
            'for' => $this->votes_for,
            'against' => $this->votes_against,
            'abstain' => $this->votes_abstain,
            'total' => $this->voters_total,
            'percent_for' => $percentFor,
            'quorum_reached' => $this->hasReachedQuorum(),
            'is_passing' => $this->isPassing(),
        ];
    }

    // ──────── دیگر helpers ────────

    public function isOverdue(): bool
    {
        if (!$this->due_date) return false;
        if ($this->status->isTerminal()) return false;
        return $this->due_date->isPast();
    }
}
