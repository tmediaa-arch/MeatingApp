<?php

declare(strict_types=1);

namespace App\Domains\Meetings\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Meetings\Enums\MeetingMode;
use App\Domains\Meetings\Enums\MeetingStatus;
use App\Domains\Meetings\Enums\MeetingType;
use App\Domains\Organization\Models\Employee;
use App\Domains\Organization\Models\OrgUnit;
use App\Domains\Organization\Models\Organization;
use App\Domains\Rooms\Models\Room;
use App\Domains\Shared\Concerns\HasAuditLog;
use App\Domains\Shared\Concerns\TracksUserChanges;
use App\Domains\Shared\Enums\ConfidentialityLevel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Meeting extends Model
{
    use HasFactory;
    use SoftDeletes;
    use HasAuditLog;
    use TracksUserChanges;

    protected $fillable = [
        'organization_id',
        'host_org_unit_id',
        'meeting_number',
        'subject',
        'description',
        'agenda_items',
        'type',
        'mode',
        'recurrence_pattern',
        'recurrence_config',
        'recurrence_parent_id',
        'confidentiality_level',
        'scheduled_start_at',
        'scheduled_end_at',
        'timezone',
        'actual_start_at',
        'actual_end_at',
        'room_id',
        'location_alt',
        'video_provider',
        'video_meeting_url',
        'video_meeting_id',
        'video_host_url',
        'video_metadata',
        'chairperson_employee_id',
        'secretary_employee_id',
        'creator_user_id',
        'status',
        'cancellation_reason',
        'cancelled_by',
        'cancelled_at',
        'allow_external_participants',
        'require_confirmation',
        'record_attendance',
        'send_reminder',
        'reminder_minutes_before',
        'allow_late_join',
        'is_recorded',
        'recording_url',
        'tags',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'agenda_items' => 'array',
        'recurrence_config' => 'array',
        'scheduled_start_at' => 'datetime',
        'scheduled_end_at' => 'datetime',
        'actual_start_at' => 'datetime',
        'actual_end_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'video_metadata' => 'array',
        'tags' => 'array',
        'metadata' => 'array',
        'allow_external_participants' => 'boolean',
        'require_confirmation' => 'boolean',
        'record_attendance' => 'boolean',
        'send_reminder' => 'boolean',
        'reminder_minutes_before' => 'integer',
        'allow_late_join' => 'boolean',
        'is_recorded' => 'boolean',
        'status' => MeetingStatus::class,
        'type' => MeetingType::class,
        'mode' => MeetingMode::class,
        'confidentiality_level' => ConfidentialityLevel::class,
    ];

    // ----------------------------- Audit ----------------------------- //

    public function auditExclude(): array
    {
        return ['updated_at'];
    }

    public function auditCategory(): string
    {
        return 'meetings';
    }

    // ----------------------------- Relations ----------------------------- //

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function hostOrgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'host_org_unit_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function chairperson(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'chairperson_employee_id');
    }

    public function secretary(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'secretary_employee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_user_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function recurrenceParent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'recurrence_parent_id');
    }

    public function recurrenceChildren(): HasMany
    {
        return $this->hasMany(self::class, 'recurrence_parent_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(MeetingParticipant::class);
    }

    public function agendaItemsRelation(): HasMany
    {
        return $this->hasMany(MeetingAgendaItem::class)->orderBy('order_index');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MeetingAttachment::class);
    }

    public function statusTransitions(): HasMany
    {
        return $this->hasMany(MeetingStatusTransition::class)->orderBy('occurred_at');
    }

    public function reservation()
    {
        return $this->hasOne(\App\Domains\Rooms\Models\RoomReservation::class);
    }

    // ─────── Phase 5 — VideoConference & ServiceRequests ───────

    public function videoConferenceRoom()
    {
        return $this->hasOne(\App\Domains\VideoConference\Models\VideoConferenceRoom::class);
    }

    public function serviceRequests(): HasMany
    {
        return $this->hasMany(\App\Domains\ServiceRequests\Models\ServiceRequest::class);
    }

    // ----------------------------- Scopes ----------------------------- //

    public function scopeUpcoming($query)
    {
        return $query->where('scheduled_start_at', '>=', now())
            ->whereNotIn('status', [MeetingStatus::Cancelled, MeetingStatus::Completed]);
    }

    public function scopePast($query)
    {
        return $query->where('scheduled_end_at', '<', now());
    }

    public function scopeBetween($query, \DateTimeInterface $from, \DateTimeInterface $until)
    {
        return $query->where(function ($q) use ($from, $until) {
            $q->where('scheduled_start_at', '<', $until)
              ->where('scheduled_end_at', '>', $from);
        });
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [
            MeetingStatus::Cancelled,
            MeetingStatus::Completed,
            MeetingStatus::Draft,
        ]);
    }

    public function scopeWithStatus($query, MeetingStatus $status)
    {
        return $query->where('status', $status);
    }

    public function scopeForUser($query, User $user)
    {
        // جلساتی که این کاربر در آن نقشی دارد
        return $query->where(function ($q) use ($user) {
            $q->where('creator_user_id', $user->id);

            if ($user->employee_id) {
                $q->orWhere('chairperson_employee_id', $user->employee_id)
                  ->orWhere('secretary_employee_id', $user->employee_id)
                  ->orWhereHas('participants', fn ($p) => $p->where('employee_id', $user->employee_id));
            }
        });
    }

    // ----------------------------- Helpers ----------------------------- //

    public function getDurationMinutesAttribute(): int
    {
        return CarbonImmutable::parse($this->scheduled_start_at)
            ->diffInMinutes($this->scheduled_end_at);
    }

    public function getActualDurationMinutesAttribute(): ?int
    {
        if (!$this->actual_start_at || !$this->actual_end_at) {
            return null;
        }
        return CarbonImmutable::parse($this->actual_start_at)
            ->diffInMinutes($this->actual_end_at);
    }

    public function isInPast(): bool
    {
        return $this->scheduled_end_at < now();
    }

    public function isInProgress(): bool
    {
        $now = now();
        return $this->scheduled_start_at <= $now && $this->scheduled_end_at >= $now
            && $this->status === MeetingStatus::InProgress;
    }

    public function canBeViewedBy(User $user): bool
    {
        // محرمانگی
        if (!$user->clearanceLevel()->canAccess($this->confidentiality_level)) {
            return false;
        }

        // creator همیشه می‌بیند
        if ($this->creator_user_id === $user->id) {
            return true;
        }

        // شرکت‌کننده
        if ($user->employee_id && $this->participants()
            ->where('employee_id', $user->employee_id)
            ->exists()
        ) {
            return true;
        }

        // رئیس یا دبیر
        if ($user->employee_id && (
            $this->chairperson_employee_id === $user->employee_id
            || $this->secretary_employee_id === $user->employee_id
        )) {
            return true;
        }

        return false;
    }

    public function canBeEditedBy(User $user): bool
    {
        if (!$this->status->isEditable()) {
            return false;
        }

        if ($this->creator_user_id === $user->id) {
            return true;
        }

        if ($user->employee_id && $this->secretary_employee_id === $user->employee_id) {
            return true;
        }

        return false;
    }

    /**
     * فرمت اعداد شماره جلسه ORG-YYYY-NNNN
     */
    public static function generateMeetingNumber(int $organizationId): string
    {
        $year = (int) now()->format('Y');

        // در یک transaction باید این enforce شود تا race condition نباشد
        // در فاز ۴ که Workflow Runtime آماده شد، می‌توان از sequence استفاده کرد
        $count = static::query()
            ->where('organization_id', $organizationId)
            ->whereYear('created_at', $year)
            ->withTrashed()
            ->count() + 1;

        $org = Organization::find($organizationId);
        $prefix = $org?->code ?? 'MTG';

        return sprintf('%s-%d-%04d', $prefix, $year, $count);
    }
}
