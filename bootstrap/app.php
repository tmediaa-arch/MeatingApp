<?php

declare(strict_types=1);

use App\Http\Middleware\SetCorrelationId;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

/**
 * نقطه ورود برنامه — Laravel 11 streamlined application bootstrap.
 *
 * این فایل به جای Kernel.php های قدیمی، middleware، routing، و exception
 * handling را پیکربندی می‌کند.
 */
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Correlation ID به همه گروه‌ها اضافه می‌شود
        $middleware->web(append: [
            SetCorrelationId::class,
        ]);

        $middleware->api(append: [
            SetCorrelationId::class,
        ]);

        // Sanctum stateful API برای کاربرانی که از SPA استفاده می‌کنند
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Phase 6 API — JSON response برای exception ها
        $exceptions->shouldRenderJsonWhen(function ($request, $e) {
            return $request->is('api/*')
                || $request->expectsJson()
                || $request->wantsJson();
        });
    })
    ->create();
