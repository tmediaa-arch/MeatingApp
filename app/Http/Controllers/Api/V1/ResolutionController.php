<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domains\Resolutions\Models\Resolution;
use App\Http\Resources\Api\V1\ResolutionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ResolutionController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Resolution::class);

        $query = QueryBuilder::for(Resolution::class)
            ->allowedFilters([
                'status',
                'priority',
                AllowedFilter::exact('meeting_id'),
                AllowedFilter::exact('minute_id'),
                AllowedFilter::exact('organization_id'),
            ])
            ->allowedSorts(['created_at', 'due_date', 'priority']);

        $resolutions = $query->paginate(min((int) $request->query('per_page', 25), 100));

        return $this->success(
            ResolutionResource::collection($resolutions),
            meta: [
                'total' => $resolutions->total(),
                'page' => $resolutions->currentPage(),
                'per_page' => $resolutions->perPage(),
                'last_page' => $resolutions->lastPage(),
            ],
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $resolution = Resolution::find($id);
        if (!$resolution) return $this->notFound('مصوبه');

        $this->authorize('view', $resolution);
        return $this->success(new ResolutionResource($resolution));
    }
}
