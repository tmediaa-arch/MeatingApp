<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Domains\Meetings\Models\Meeting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Meeting
 */
class MeetingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'meeting_number' => $this->meeting_number,
            'subject' => $this->subject,
            'description' => $this->description,
            'status' => $this->status?->value,
            'meeting_type' => $this->meeting_type?->value,
            'priority' => $this->priority,
            'confidentiality_level' => $this->confidentiality_level?->value,
            'scheduled_start_at' => $this->scheduled_start_at?->toIso8601String(),
            'scheduled_end_at' => $this->scheduled_end_at?->toIso8601String(),
            'actual_start_at' => $this->actual_start_at?->toIso8601String(),
            'actual_end_at' => $this->actual_end_at?->toIso8601String(),
            'host_user_id' => $this->host_user_id,
            'host_org_unit_id' => $this->host_org_unit_id,
            'room_id' => $this->room_id,
            'has_video_conference' => (bool) $this->has_video_conference,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // روابط lazy-loaded
            'participants' => $this->whenLoaded('participants', fn () =>
                $this->participants->map(fn ($p) => [
                    'user_id' => $p->user_id,
                    'role' => $p->role?->value ?? null,
                    'attendance_status' => $p->attendance_status?->value ?? null,
                ])->toArray()
            ),
            'room' => $this->whenLoaded('room', fn () => [
                'id' => $this->room->id,
                'name' => $this->room->name,
                'location' => $this->room->location,
            ]),
        ];
    }
}
