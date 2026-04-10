<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Domains\Minutes\Models\Minute;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Minute
 */
class MinuteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'minute_number' => $this->minute_number,
            'title' => $this->title,
            'status' => $this->status?->value,
            'current_version' => $this->current_version,
            'meeting_id' => $this->meeting_id,
            'secretary_user_id' => $this->secretary_user_id,
            'chairperson_user_id' => $this->chairperson_user_id,
            'secretary_signed_at' => $this->secretary_signed_at?->toIso8601String(),
            'chairperson_signed_at' => $this->chairperson_signed_at?->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),
            'content_hash' => $this->content_hash,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
