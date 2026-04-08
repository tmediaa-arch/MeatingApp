<?php

declare(strict_types=1);

namespace App\Domains\Dashboards\Widgets;

use App\Domains\Dashboards\Contracts\DashboardWidgetInterface;
use App\Domains\Dashboards\DTOs\WidgetData;
use App\Domains\Dashboards\Models\DashboardWidget;
use App\Domains\Identity\Models\User;

abstract class AbstractDashboardWidget implements DashboardWidgetInterface
{
    abstract public function getData(DashboardWidget $widget, User $user, array $filters = []): WidgetData;

    public function isCacheable(): bool
    {
        return true;
    }

    public function getCacheTtlSeconds(): int
    {
        return 300; // 5 minutes
    }
}
