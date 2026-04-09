<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

abstract class BaseApiController extends Controller
{
    protected function success(
        JsonResource|ResourceCollection|array $data,
        int $status = 200,
        array $meta = [],
    ): JsonResponse {
        $payload = ['data' => $data];
        if (!empty($meta)) {
            $payload['meta'] = $meta;
        }
        return response()->json($payload, $status);
    }

    protected function error(string $message, int $status = 400, array $errors = []): JsonResponse
    {
        return response()->json([
            'error' => $message,
            'errors' => $errors,
        ], $status);
    }

    protected function notFound(string $resource = 'منبع'): JsonResponse
    {
        return $this->error("{$resource} یافت نشد.", 404);
    }
}
