<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domains\Meetings\Actions\CreateMeetingAction;
use App\Domains\Meetings\Models\Meeting;
use App\Http\Resources\Api\V1\MeetingResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class MeetingController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Meeting::class);

        $query = QueryBuilder::for(Meeting::class)
            ->allowedFilters([
                'status',
                'meeting_type',
                'priority',
                AllowedFilter::scope('scheduled_after', 'scheduledAfter'),
                AllowedFilter::scope('scheduled_before', 'scheduledBefore'),
                AllowedFilter::exact('host_user_id'),
                AllowedFilter::exact('organization_id'),
            ])
            ->allowedSorts(['scheduled_start_at', 'created_at', 'updated_at'])
            ->allowedIncludes(['room', 'participants']);

        $meetings = $query->paginate(min((int) $request->query('per_page', 25), 100));

        return $this->success(
            MeetingResource::collection($meetings),
            meta: [
                'total' => $meetings->total(),
                'page' => $meetings->currentPage(),
                'per_page' => $meetings->perPage(),
                'last_page' => $meetings->lastPage(),
            ],
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $meeting = Meeting::with(['room', 'participants'])->find($id);

        if (!$meeting) {
            return $this->notFound('جلسه');
        }

        $this->authorize('view', $meeting);

        return $this->success(new MeetingResource($meeting));
    }

    public function store(Request $request, CreateMeetingAction $action): JsonResponse
    {
        $this->authorize('create', Meeting::class);

        $validated = $request->validate([
            'subject' => 'required|string|max:500',
            'description' => 'nullable|string',
            'meeting_type' => 'required|string',
            'scheduled_start_at' => 'required|date',
            'scheduled_end_at' => 'required|date|after:scheduled_start_at',
            'room_id' => 'nullable|integer|exists:rooms,id',
            'host_org_unit_id' => 'nullable|integer|exists:org_units,id',
            'priority' => 'nullable|integer',
            'confidentiality_level' => 'nullable|string',
            'participant_user_ids' => 'nullable|array',
            'participant_user_ids.*' => 'integer|exists:users,id',
        ]);

        try {
            $meeting = $action->execute(
                $validated,
                $request->user(),
            );

            return $this->success(new MeetingResource($meeting), 201);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $meeting = Meeting::find($id);
        if (!$meeting) return $this->notFound('جلسه');

        $this->authorize('delete', $meeting);

        $meeting->delete();
        return response()->json(null, 204);
    }
}
