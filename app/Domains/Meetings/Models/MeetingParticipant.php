<?php

declare(strict_types=1);

namespace App\Domains\Meetings\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\UserDelegation;
use App\Domains\Meetings\Enums\AttendanceStatus;
use App\Domains\Meetings\Enums\InvitationStatus;
use App\Domains\Meetings\Enums\ParticipantRole;
use App\Domains\Organization\Models\Employee;
use App\Domains\Shared\Concerns\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeetingParticipant extends Model
{
    use HasFactory;
    use HasAuditLog;

    protected $fillable = [
        'meeting_id',
        'employee_id',
        'user_id',
        'external_full_name',
        'external_email',
        'external_mobile',
        'external_organization',
        'external_title',
        'external_national_code',
        'role',
        'is_mandatory',
        'is_external',
        'order_index',
        'invitation_status',
        'invitation_responded_at',
        'response_note',
        'attendance_status',
        'joined_at',
        'left_at',
        'attendance_minutes',
        'represented_by_employee_id',
        'delegation_id',
        'signed_minutes',
        'signed_at',
        'signature_method',
        'metadata',
    ];

    protected $casts = [
        'is_mandatory' => 'boolean',
        'is_external' => 'boolean',
        'signed_minutes' => 'boolean',
        'invitation_responded_at' => 'datetime',
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
        'signed_at' => 'datetime',
        'order_index' => 'integer',
        'attendance_minutes' => 'integer',
        'metadata' => 'array',
        'role' => ParticipantRole::class,
        'invitation_status' => InvitationStatus::class,
        'attendance_status' => AttendanceStatus::class,
    ];

    // ----------------------------- Relations ----------------------------- //

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function representedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'represented_by_employee_id');
    }

    public function delegation(): BelongsTo
    {
        return $this->belongsTo(UserDelegation::class, 'delegation_id');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(\App\Domains\Invitations\Models\Invitation::class, 'participant_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(\App\Domains\Invitations\Models\InvitationResponse::class, 'participant_id');
    }

    // ----------------------------- Helpers ----------------------------- //

    public function getDisplayNameAttribute(): string
    {
        if ($this->is_external) {
            $org = $this->external_organization ? " ({$this->external_organization})" : '';
            return $this->external_full_name . $org;
        }

        return $this->employee?->full_name ?? '—';
    }

    public function getEmailAttribute()
    {
        if ($this->is_external) {
            return $this->external_email;
        }
        return $this->employee?->work_email ?? $this->user?->email;
    }

    public function getMobileAttribute()
    {
        if ($this->is_external) {
            return $this->external_mobile;
        }
        return $this->employee?->mobile ?? $this->user?->mobile;
    }

    public function getEffectiveAttendeeNameAttribute(): string
    {
        if ($this->represented_by_employee_id) {
            return $this->representedBy->full_name . ' (به نمایندگی از ' . $this->display_name . ')';
        }
        return $this->display_name;
    }

    public function canRespondInvitation(): bool
    {
        return in_array($this->invitation_status, [
            InvitationStatus::Invited,
            InvitationStatus::Tentative,
        ], true);
    }
}
