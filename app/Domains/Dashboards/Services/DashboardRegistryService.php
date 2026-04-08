<?php

declare(strict_types=1);

namespace App\Domains\Dashboards\Services;

use App\Domains\Dashboards\Contracts\DashboardWidgetInterface;
use App\Domains\Dashboards\Models\Dashboard;
use App\Domains\Dashboards\Models\DashboardWidget;

/**
 * DashboardRegistryService — رجیستری مرکزی داشبوردها (code-first).
 *
 * مشابه ReportRegistryService، دشبوردها به صورت اعلانی در یک ServiceProvider
 * تعریف می‌شوند و در boot به DB sync می‌شوند.
 */
class DashboardRegistryService
{
    /**
     * @var array<int, array{key:string, name:string, roles:array, icon:?string, color:?string, widgets:array}>
     */
    private array $registry = [];

    public function defineDashboard(
        string $key,
        string $displayName,
        array $allowedRoles = [],
        ?string $icon = null,
        ?string $color = null,
        ?string $description = null,
    ): DashboardBuilder {
        return new DashboardBuilder($this, $key, $displayName, $allowedRoles, $icon, $color, $description);
    }

    public function add(array $definition): void
    {
        $this->registry[] = $definition;
    }

    public function all(): array
    {
        return $this->registry;
    }

    public function syncToDatabase(): int
    {
        $count = 0;

        foreach ($this->registry as $entry) {
            $dashboard = Dashboard::updateOrCreate(
                ['organization_id' => null, 'key' => $entry['key']],
                [
                    'display_name' => $entry['name'],
                    'description' => $entry['description'] ?? null,
                    'allowed_roles' => $entry['roles'],
                    'icon' => $entry['icon'],
                    'color' => $entry['color'],
                    'is_active' => true,
                    'sort_order' => $entry['sort_order'] ?? 0,
                ],
            );

            // ویجت‌ها — delete-and-recreate (since this is system definition)
            $dashboard->widgets()->delete();

            foreach ($entry['widgets'] as $widget) {
                /** @var DashboardWidgetInterface $handler */
                $handler = app($widget['class']);

                DashboardWidget::create([
                    'dashboard_id' => $dashboard->id,
                    'key' => $widget['key'],
                    'display_name' => $widget['name'],
                    'widget_class' => $widget['class'],
                    'type' => $widget['type'],
                    'chart_type' => $widget['chart_type'] ?? null,
                    'row' => $widget['row'] ?? 0,
                    'column' => $widget['column'] ?? 0,
                    'width' => $widget['width'] ?? 4,
                    'height' => $widget['height'] ?? 1,
                    'config' => $widget['config'] ?? null,
                    'refresh_interval_seconds' => $widget['refresh_seconds'] ?? 0,
                    'is_cacheable' => $handler->isCacheable(),
                    'is_active' => true,
                ]);
            }

            $count++;
        }

        return $count;
    }
}

/**
 * Fluent builder برای تعریف داشبورد در ServiceProvider.
 */
class DashboardBuilder
{
    private array $widgets = [];

    public function __construct(
        private DashboardRegistryService $registry,
        private string $key,
        private string $displayName,
        private array $allowedRoles,
        private ?string $icon,
        private ?string $color,
        private ?string $description,
        private int $sortOrder = 0,
    ) {
    }

    public function widget(
        string $widgetClass,
        string $key,
        string $name,
        string $type,
        int $row = 0,
        int $column = 0,
        int $width = 4,
        int $height = 1,
        ?string $chartType = null,
        array $config = [],
        int $refreshSeconds = 0,
    ): self {
        $this->widgets[] = [
            'class' => $widgetClass,
            'key' => $key,
            'name' => $name,
            'type' => $type,
            'chart_type' => $chartType,
            'row' => $row,
            'column' => $column,
            'width' => $width,
            'height' => $height,
            'config' => $config,
            'refresh_seconds' => $refreshSeconds,
        ];

        return $this;
    }

    public function sortOrder(int $order): self
    {
        $this->sortOrder = $order;
        return $this;
    }

    public function register(): void
    {
        $this->registry->add([
            'key' => $this->key,
            'name' => $this->displayName,
            'description' => $this->description,
            'roles' => $this->allowedRoles,
            'icon' => $this->icon,
            'color' => $this->color,
            'sort_order' => $this->sortOrder,
            'widgets' => $this->widgets,
        ]);
    }
}
