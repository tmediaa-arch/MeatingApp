<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domains\Tasks\Models\Task;
use App\Http\Resources\Api\V1\TaskResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class TaskController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Task::class);

        $query = QueryBuilder::for(Task::class)
            ->allowedFilters([
                'status',
                'priority',
                AllowedFilter::exact('assignee_user_id'),
                AllowedFilter::exact('organization_id'),
                AllowedFilter::scope('overdue'),
                AllowedFilter::callback('due_before', fn ($q, $v) => $q->where('due_date', '<=', $v)),
                AllowedFilter::callback('due_after', fn ($q, $v) => $q->where('due_date', '>=', $v)),
            ])
            ->allowedSorts(['due_date', 'priority', 'created_at', 'progress_percent'])
            ->allowedIncludes(['assignee']);

        $tasks = $query->paginate(min((int) $request->query('per_page', 25), 100));

        return $this->success(
            TaskResource::collection($tasks),
            meta: [
                'total' => $tasks->total(),
                'page' => $tasks->currentPage(),
                'per_page' => $tasks->perPage(),
                'last_page' => $tasks->lastPage(),
            ],
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $task = Task::with(['assignee'])->find($id);
        if (!$task) return $this->notFound('وظیفه');

        $this->authorize('view', $task);
        return $this->success(new TaskResource($task));
    }

    public function updateProgress(Request $request, int $id): JsonResponse
    {
        $task = Task::find($id);
        if (!$task) return $this->notFound('وظیفه');

        $this->authorize('update', $task);

        $validated = $request->validate([
            'progress_percent' => 'required|integer|min:0|max:100',
            'note' => 'nullable|string|max:2000',
        ]);

        $task->forceFill([
            'progress_percent' => $validated['progress_percent'],
        ])->save();

        return $this->success(new TaskResource($task->fresh()));
    }
}
