<?php

declare(strict_types=1);

namespace App\Domains\VideoConference\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Organization\Models\Employee;
use App\Domains\VideoConference\Enums\AttendanceRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ثبت رویداد join/leave — append-only.
 *
 * @property int $id
 * @property int $room_id
 * @property int|null $user_id
 * @property string $display_name
 * @property AttendanceRole $role
 * @property string $event_type   joined | left
 * @property \Carbon\Carbon $occurred_at
 */
class VideoConferenceAttendance extends Model
{
    use HasFactory;

    protected $table = 'video_conference_attendance';
    public $timestamps = false; // فقط created_at

    protected static function newFactory()
    {
        return \Database\Factories\VideoConferenceAttendanceFactory::new();
    }

    protected $fillable = [
        'room_id',
        'meeting_id',
        'user_id',
        'employee_id',
        'display_name',
        'email',
        'role',
        'event_type',
        'occurred_at',
        'client_ip',
        'user_agent',
        'external_session_id',
        'metadata',
    ];

    protected $casts = [
        'role' => AttendanceRole::class,
        'occurred_at' => 'datetime',
        'created_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $a) {
            if (empty($a->occurred_at)) {
                $a->occurred_at = now();
            }
        });

        static::updating(function () {
            throw new \LogicException('VideoConferenceAttendance append-only است و قابل ویرایش نیست.');
        });

        static::deleting(function () {
            throw new \LogicException('VideoConferenceAttendance append-only است و قابل حذف نیست.');
        });
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(VideoConferenceRoom::class, 'room_id');
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function scopeJoined(Builder $q): Builder
    {
        return $q->where('event_type', 'joined');
    }

    public function scopeLeft(Builder $q): Builder
    {
        return $q->where('event_type', 'left');
    }

    public function scopeForRoom(Builder $q, int $roomId): Builder
    {
        return $q->where('room_id', $roomId);
    }
}
