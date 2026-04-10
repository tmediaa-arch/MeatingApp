<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Domains\Tasks\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Task
 */
class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'task_number' => $this->task_number,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status?->value,
            'priority' => $this->priority?->value,
            'progress_percent' => $this->progress_percent,
            'assignee_user_id' => $this->assignee_user_id,
            'creator_user_id' => $this->creator_user_id,
            'due_date' => $this->due_date?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'is_overdue' => (bool) $this->is_overdue,
            'escalation_level' => $this->escalation_level,
            'resolution_id' => $this->resolution_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'assignee' => $this->whenLoaded('assignee', fn () => [
                'id' => $this->assignee?->id,
                'name' => $this->assignee?->name,
            ]),
        ];
    }
}
