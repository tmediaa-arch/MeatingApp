<?php

declare(strict_types=1);

namespace App\Domains\Invitations\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\UserDelegation;
use App\Domains\Meetings\Models\MeetingParticipant;
use App\Domains\Organization\Models\Employee;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvitationResponse extends Model
{
    use HasFactory;

    protected $fillable = [
        'participant_id',
        'invitation_id',
        'response',
        'note',
        'responded_by_user_id',
        'delegation_id',
        'proposed_substitute_employee_id',
        'response_method',
        'response_ip',
        'responded_at',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
    ];

    /**
     * این رکوردها append-only هستند — هر تغییر نظر، رکورد جدید است
     */
    protected static function booted(): void
    {
        static::updating(function () {
            throw new \LogicException(
                'InvitationResponse records are append-only — create a new response instead of updating.'
            );
        });
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(MeetingParticipant::class, 'participant_id');
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class);
    }

    public function respondedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by_user_id');
    }

    public function delegation(): BelongsTo
    {
        return $this->belongsTo(UserDelegation::class, 'delegation_id');
    }

    public function proposedSubstitute(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'proposed_substitute_employee_id');
    }
}
