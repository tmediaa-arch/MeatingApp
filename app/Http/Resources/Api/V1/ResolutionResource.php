<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Domains\Resolutions\Models\Resolution;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Resolution
 */
class ResolutionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'resolution_number' => $this->resolution_number,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status?->value,
            'priority' => $this->priority?->value,
            'meeting_id' => $this->meeting_id,
            'minute_id' => $this->minute_id,
            'due_date' => $this->due_date?->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
