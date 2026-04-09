<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domains\Minutes\Models\Minute;
use App\Http\Resources\Api\V1\MinuteResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class MinuteController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Minute::class);

        $query = QueryBuilder::for(Minute::class)
            ->allowedFilters([
                'status',
                AllowedFilter::exact('meeting_id'),
                AllowedFilter::exact('organization_id'),
            ])
            ->allowedSorts(['published_at', 'created_at']);

        $minutes = $query->paginate(min((int) $request->query('per_page', 25), 100));

        return $this->success(
            MinuteResource::collection($minutes),
            meta: [
                'total' => $minutes->total(),
                'page' => $minutes->currentPage(),
                'per_page' => $minutes->perPage(),
                'last_page' => $minutes->lastPage(),
            ],
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $minute = Minute::find($id);
        if (!$minute) return $this->notFound('صورتجلسه');

        $this->authorize('view', $minute);
        return $this->success(new MinuteResource($minute));
    }
}
