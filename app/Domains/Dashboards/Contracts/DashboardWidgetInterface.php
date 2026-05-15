<?php

declare(strict_types=1);

namespace App\Domains\Dashboards\Contracts;

use App\Domains\Dashboards\DTOs\WidgetData;
use App\Domains\Dashboards\Models\DashboardWidget;
use App\Domains\Identity\Models\User;

/**
 * DashboardWidgetInterface — قرارداد همه widget های داشبورد.
 *
 * AbstractDashboardWidget این interface را پیاده‌سازی می‌کند.
 */
interface DashboardWidgetInterface
{
    /**
     * تولید داده widget برای یک کاربر مشخص.
     *
     * @param array<string, mixed> $filters
     */
    public function getData(DashboardWidget $widget, User $user, array $filters = []): WidgetData;

    /**
     * آیا داده این widget قابل cache است؟
     */
    public function isCacheable(): bool;

    /**
     * مدت اعتبار cache بر حسب ثانیه.
     */
    public function getCacheTtlSeconds(): int;
}
