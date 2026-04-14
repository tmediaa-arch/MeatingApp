<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Audit\Services\AuditService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class SetCorrelationId
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $correlationId = $request->header('X-Correlation-Id') ?? (string) Str::uuid();
        $this->auditService->setCorrelationId($correlationId);

        $response = $next($request);

        try {
            $response->headers->set('X-Correlation-Id', $correlationId);
        } catch (\Throwable) {
            // برخی response ها (مثل BinaryFileResponse) headers را allow نمی‌کنند
        }

        return $response;
    }
}