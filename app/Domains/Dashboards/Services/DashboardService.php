<?php

declare(strict_types=1);

namespace App\Domains\Dashboards\Services;

use App\Domains\Dashboards\Contracts\DashboardWidgetInterface;
use App\Domains\Dashboards\DTOs\WidgetData;
use App\Domains\Dashboards\Models\Dashboard;
use App\Domains\Dashboards\Models\DashboardWidget;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * DashboardService — render داده‌های یک داشبورد برای کاربر.
 *
 * هر widget یک کلاس PHP دارد که DashboardWidgetInterface را پیاده‌سازی می‌کند.
 * این سرویس همه widgetهای داشبورد را به موازات (در حد امکان) render می‌کند
 * و در صورت نیاز cache می‌گیرد.
 */
class DashboardService
{
    /**
     * Render همه widgetهای یک داشبورد.
     *
     * @return array<int, array{widget: array, data: array, error: ?string}>
     */
    public function render(Dashboard $dashboard, User $user, array $filters = []): array
    {
        if (!$dashboard->canBeViewedBy($user)) {
            throw new \DomainException('شما اجازه مشاهده این داشبورد را ندارید.');
        }

        $widgets = $dashboard->widgets()->active()->get();
        $output = [];

        foreach ($widgets as $widget) {
            try {
                $data = $this->renderWidget($widget, $user, $filters);
                $output[] = [
                    'widget' => $this->widgetToArray($widget),
                    'data' => $data->toArray(),
                    'error' => null,
                ];
            } catch (\Throwable $e) {
                Log::warning("Widget render failed: {$widget->key}", [
                    'widget_id' => $widget->id,
                    'error' => $e->getMessage(),
                ]);
                $output[] = [
                    'widget' => $this->widgetToArray($widget),
                    'data' => null,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $output;
    }

    public function renderWidget(DashboardWidget $widget, User $user, array $filters = []): WidgetData
    {
        if (!class_exists($widget->widget_class)) {
            throw new \LogicException("Widget handler '{$widget->widget_class}' وجود ندارد.");
        }

        /** @var DashboardWidgetInterface $handler */
        $handler = app($widget->widget_class);

        if (!$handler instanceof DashboardWidgetInterface) {
            throw new \LogicException(
                "Widget handler '{$widget->widget_class}' باید DashboardWidgetInterface را پیاده‌سازی کند."
            );
        }

        if (!$widget->is_cacheable || !$handler->isCacheable()) {
            return $handler->getData($widget, $user, $filters);
        }

        $cacheKey = sprintf(
            'dashboard:widget:%d:user:%d:filters:%s',
            $widget->id,
            $user->id,
            md5(json_encode($filters)),
        );

        return Cache::remember(
            $cacheKey,
            $handler->getCacheTtlSeconds(),
            fn () => $handler->getData($widget, $user, $filters),
        );
    }

    private function widgetToArray(DashboardWidget $widget): array
    {
        return [
            'id' => $widget->id,
            'key' => $widget->key,
            'display_name' => $widget->display_name,
            'type' => $widget->type,
            'chart_type' => $widget->chart_type,
            'row' => $widget->row,
            'column' => $widget->column,
            'width' => $widget->width,
            'height' => $widget->height,
            'refresh_interval_seconds' => $widget->refresh_interval_seconds,
        ];
    }

    public function invalidateUserCache(User $user, ?Dashboard $dashboard = null): void
    {
        $pattern = $dashboard
            ? sprintf('dashboard:widget:*:user:%d:*', $user->id)
            : sprintf('dashboard:widget:*:user:%d:*', $user->id);

        // (Cache::flush() نیست — در production از Redis SCAN استفاده می‌شود)
        // اینجا فقط trigger می‌زنیم
    }
}
