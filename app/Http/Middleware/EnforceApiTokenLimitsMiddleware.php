<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Integrations\Models\ApiTokenMetadata;
use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnforceApiTokenLimits — اعمال محدودیت‌های token level.
 *
 * بررسی‌ها:
 * 1. token معتبر و expired نیست
 * 2. IP در allowed_ips است (در صورت تنظیم)
 * 3. rate limit (per minute و per day) رعایت می‌شود
 * 4. ثبت آمار استفاده
 */
class EnforceApiTokenLimitsMiddleware
{
    public function __construct(private readonly RateLimiter $limiter)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();
        if (!$token) {
            return response()->json(['error' => 'احراز هویت ناموفق'], 401);
        }

        $metadata = ApiTokenMetadata::where('token_id', $token->id)->first();
        if ($metadata) {
            // 1. expiration / revocation
            if (!$metadata->isActive()) {
                return response()->json([
                    'error' => 'این token منقضی یا باطل شده است',
                    'revoked_at' => $metadata->revoked_at?->toIso8601String(),
                    'expires_at' => $metadata->expires_at?->toIso8601String(),
                ], 401);
            }

            // 2. IP check
            $ip = $request->ip() ?? '';
            if (!$metadata->isIpAllowed($ip)) {
                return response()->json([
                    'error' => 'IP شما در whitelist این token نیست',
                    'ip' => $ip,
                ], 403);
            }

            // 3. rate limit per minute
            $minuteKey = "api_token:{$token->id}:minute";
            if ($this->limiter->tooManyAttempts($minuteKey, $metadata->rate_limit_per_minute)) {
                return $this->rateLimitResponse($this->limiter->availableIn($minuteKey));
            }
            $this->limiter->hit($minuteKey, 60);

            // 4. rate limit per day
            $dayKey = "api_token:{$token->id}:day";
            if ($this->limiter->tooManyAttempts($dayKey, $metadata->rate_limit_per_day)) {
                return $this->rateLimitResponse($this->limiter->availableIn($dayKey));
            }
            $this->limiter->hit($dayKey, 86400);

            // 5. ثبت آمار
            $metadata->recordUsage($ip);
        }

        $response = $next($request);

        // header های rate limit
        if ($metadata) {
            $response->headers->set('X-RateLimit-Limit', (string) $metadata->rate_limit_per_minute);
            $response->headers->set(
                'X-RateLimit-Remaining',
                (string) $this->limiter->remaining("api_token:{$token->id}:minute", $metadata->rate_limit_per_minute),
            );
        }

        return $response;
    }

    private function rateLimitResponse(int $retryAfter): Response
    {
        return response()->json([
            'error' => 'تعداد درخواست از حد مجاز فراتر رفته است',
            'retry_after_seconds' => $retryAfter,
        ], 429)->header('Retry-After', (string) $retryAfter);
    }
}
