<?php

declare(strict_types=1);

namespace App\Domains\VideoConference\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\VideoConference\Enums\VideoConferenceDriver;
use App\Domains\VideoConference\Enums\VideoConferenceRoomStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $room_uuid
 * @property int|null $meeting_id
 * @property int $provider_id
 * @property string $external_room_id
 * @property string $host_url
 * @property string $attendee_url
 * @property string|null $moderator_password
 * @property string|null $attendee_password
 * @property string $subject
 * @property VideoConferenceRoomStatus $status
 * @property VideoConferenceDriver $driver
 */
class VideoConferenceRoom extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'video_conference_rooms';

    protected static function newFactory()
    {
        return \Database\Factories\VideoConferenceRoomFactory::new();
    }

    protected $fillable = [
        'room_uuid',
        'meeting_id',
        'provider_id',
        'driver',
        'external_room_id',
        'host_url',
        'attendee_url',
        'moderator_password',
        'attendee_password',
        'subject',
        'max_participants',
        'require_password',
        'waiting_room_enabled',
        'recording_enabled',
        'scheduled_start_at',
        'scheduled_end_at',
        'actual_start_at',
        'actual_end_at',
        'status',
        'recording_url',
        'recording_status',
        'recording_duration_seconds',
        'recording_size_bytes',
        'provider_metadata',
        'created_by_user_id',
    ];

    protected $casts = [
        'driver' => VideoConferenceDriver::class,
        'status' => VideoConferenceRoomStatus::class,
        'require_password' => 'boolean',
        'waiting_room_enabled' => 'boolean',
        'recording_enabled' => 'boolean',
        'scheduled_start_at' => 'datetime',
        'scheduled_end_at' => 'datetime',
        'actual_start_at' => 'datetime',
        'actual_end_at' => 'datetime',
        'provider_metadata' => 'array',
    ];

    protected $hidden = [
        // رمزها در API responses ظاهر نشوند
        'moderator_password',
        'attendee_password',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $room) {
            if (empty($room->room_uuid)) {
                $room->room_uuid = (string) Str::uuid();
            }
        });
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(VideoConferenceProvider::class, 'provider_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(VideoConferenceAttendance::class, 'room_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereIn('status', [
            VideoConferenceRoomStatus::Scheduled->value,
            VideoConferenceRoomStatus::Starting->value,
            VideoConferenceRoomStatus::InProgress->value,
        ]);
    }

    public function scopeInProgress(Builder $q): Builder
    {
        return $q->where('status', VideoConferenceRoomStatus::InProgress);
    }

    public function isActive(): bool
    {
        return !$this->status->isTerminal();
    }

    public function durationMinutes(): ?int
    {
        if (!$this->actual_start_at) return null;
        $end = $this->actual_end_at ?? now();
        return $this->actual_start_at->diffInMinutes($end);
    }
}
