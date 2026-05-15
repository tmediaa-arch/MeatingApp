<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * `server` — نام مستعار برای دستور built-in `serve`.
 * هر دو شکل کار می‌کنند: `php artisan serve` و `php artisan server`.
 */
Artisan::command(
    'server {--host= : آدرس host} {--port= : شماره port} {--tries= : تعداد port برای تلاش} {--no-reload : عدم reload هنگام تغییر .env}',
    function () {
        $params = [];
        foreach (['host', 'port', 'tries'] as $option) {
            $value = $this->option($option);
            if ($value !== null && $value !== '') {
                $params['--' . $option] = $value;
            }
        }
        if ($this->option('no-reload')) {
            $params['--no-reload'] = true;
        }

        return $this->call('serve', $params);
    },
)->purpose('نام مستعار serve: اجرای سرور توسعه محلی');

// ─────────────────────────────────────────────────────────
// Scheduled commands
// ─────────────────────────────────────────────────────────

// Phase 3 — Tasks escalation: روزانه ساعت 8 صبح
Schedule::command('tasks:escalate')
    ->dailyAt('08:00')
    ->name('tasks-daily-escalation')
    ->withoutOverlapping()
    ->onOneServer();

// Phase 3 — Notifications retry: هر 15 دقیقه
Schedule::command('notifications:retry')
    ->everyFifteenMinutes()
    ->name('notifications-retry')
    ->withoutOverlapping()
    ->onOneServer();

// Phase 4 — Workflow timer tick: هر دقیقه
Schedule::command('workflow:timer-tick')
    ->everyMinute()
    ->name('workflow-timer-tick')
    ->withoutOverlapping()
    ->onOneServer();

// Phase 4 — Workflow SLA check: هر ساعت
Schedule::command('workflow:sla-check')
    ->hourly()
    ->name('workflow-sla-check')
    ->withoutOverlapping()
    ->onOneServer();

// Phase 5 — VC health check: هر 5 دقیقه
Schedule::command('vc:health-check')
    ->everyFiveMinutes()
    ->name('vc-health-check')
    ->withoutOverlapping()
    ->onOneServer();

// Phase 5 — VC status sync: هر دقیقه (برای تشخیص شروع/پایان اتاق‌ها)
Schedule::command('vc:sync-status')
    ->everyMinute()
    ->name('vc-status-sync')
    ->withoutOverlapping()
    ->onOneServer();

// ─────────────────────────────────────────────────────────
// Phase 6 — Reports, Integrations, Webhooks, Exports
// ─────────────────────────────────────────────────────────

// Phase 6 — اجرای schedule های گزارش: هر دقیقه
Schedule::command('reports:run-schedules')
    ->everyMinute()
    ->name('reports-run-schedules')
    ->withoutOverlapping()
    ->onOneServer();

// Phase 6 — sync providers due: هر دقیقه
Schedule::command('integrations:sync-due')
    ->everyMinute()
    ->name('integrations-sync-due')
    ->withoutOverlapping()
    ->onOneServer();

// Phase 6 — retry failed webhooks: هر 5 دقیقه
Schedule::command('webhooks:retry-failed')
    ->everyFiveMinutes()
    ->name('webhooks-retry-failed')
    ->withoutOverlapping()
    ->onOneServer();

// Phase 6 — cleanup expired exports: روزانه ساعت ۳ بامداد
Schedule::command('exports:cleanup-expired')
    ->dailyAt('03:00')
    ->name('exports-cleanup-expired')
    ->withoutOverlapping()
    ->onOneServer();
