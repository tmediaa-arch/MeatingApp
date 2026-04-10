<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Exports\Generators\CalendarIcsGenerator;
use App\Domains\Exports\Models\CalendarFeedToken;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CalendarFeedController — endpoint عمومی ICS feed.
 *
 * URL مثال:
 *   GET /calendar/feed/abc123token456.ics
 *
 * Token در URL است؛ هیچ Auth header لازم نیست (که با subscribers
 * مثل Outlook/Google Calendar سازگار باشد).
 *
 * Security:
 * - فقط token معتبر و active قابل دسترسی است
 * - last_accessed_at و access_count ثبت می‌شود
 */
class CalendarFeedController extends Controller
{
    public function show(Request $request, string $token, CalendarIcsGenerator $generator): Response
    {
        // پسوند .ics از URL حذف می‌شود اگر هست
        $token = preg_replace('/\.ics$/i', '', $token);

        $feedToken = CalendarFeedToken::query()
            ->active()
            ->where('token', $token)
            ->with('user')
            ->first();

        if (!$feedToken) {
            return response('Token not found or expired', 404);
        }

        $feedToken->recordAccess();

        $ics = $generator->generateForUser(
            user: $feedToken->user,
            filterConfig: $feedToken->filter_config ?? [],
        );

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="calendar.ics"',
            'Cache-Control' => 'private, max-age=600', // 10 min cache
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
