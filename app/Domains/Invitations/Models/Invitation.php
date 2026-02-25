<?php

declare(strict_types=1);

namespace App\Domains\Invitations\Models;

use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Models\MeetingParticipant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'meeting_id',
        'participant_id',
        'type',
        'channel',
        'to_address',
        'subject',
        'body',
        'ical_uid',
        'ical_sequence',
        'status',
        'scheduled_at',
        'sent_at',
        'delivered_at',
        'opened_at',
        'responded_at',
        'error_message',
        'retry_count',
        'next_retry_at',
        'external_id',
        'response_token',
        'response_token_expires_at',
        'metadata',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'opened_at' => 'datetime',
        'responded_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'response_token_expires_at' => 'datetime',
        'retry_count' => 'integer',
        'ical_sequence' => 'integer',
        'metadata' => 'array',
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(MeetingParticipant::class, 'participant_id');
    }

    public function scopeQueued($query)
    {
        return $query->whereIn('status', ['queued', 'failed'])
            ->where(function ($q) {
                $q->whereNull('next_retry_at')
                  ->orWhere('next_retry_at', '<=', now());
            });
    }

    public function scopeDueForSending($query)
    {
        return $query->where('status', 'queued')
            ->where(function ($q) {
                $q->whereNull('scheduled_at')
                  ->orWhere('scheduled_at', '<=', now());
            });
    }
}
