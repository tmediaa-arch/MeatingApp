<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domains\Dashboards\Models\Dashboard;
use App\Domains\Dashboards\Services\DashboardService;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

/**
 * صفحه نمایش داشبورد — هر کاربر می‌تواند داشبوردهای مجاز خود را ببیند.
 *
 * Widget ها در گرید ۱۲ ستونی Chart.js / table / list render می‌شوند.
 */
class DashboardViewPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-pie';
    protected static ?string $navigationLabel = 'داشبوردها';
    protected static ?string $navigationGroup = 'داشبوردها';
    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.dashboard-view';

    public ?int $selectedDashboardId = null;
    public ?Dashboard $currentDashboard = null;
    public array $renderedWidgets = [];
    public array $filters = [];

    public function mount(): void
    {
        $user = Auth::user();

        $dashboards = Dashboard::query()->forUser($user)->orderBy('sort_order')->get();

        if ($dashboards->isEmpty()) {
            return;
        }

        $pinned = $user->dashboardPreferences()
            ->where('is_pinned', true)
            ->whereIn('dashboard_id', $dashboards->pluck('id'))
            ->first();

        $this->selectedDashboardId = $pinned?->dashboard_id ?? $dashboards->first()->id;

        $this->loadDashboard();
    }

    public function loadDashboard(): void
    {
        if (!$this->selectedDashboardId) return;

        $this->currentDashboard = Dashboard::find($this->selectedDashboardId);
        if (!$this->currentDashboard) return;

        $this->renderedWidgets = app(DashboardService::class)
            ->render($this->currentDashboard, Auth::user(), $this->filters);
    }

    public function switchDashboard(int $dashboardId): void
    {
        $this->selectedDashboardId = $dashboardId;
        $this->loadDashboard();
    }

    public function refreshDashboard(): void
    {
        $this->loadDashboard();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return Auth::check()
            && Dashboard::query()->forUser(Auth::user())->exists();
    }

    protected function getViewData(): array
    {
        return [
            'dashboards' => Dashboard::query()->forUser(Auth::user())->orderBy('sort_order')->get(),
            'currentDashboard' => $this->currentDashboard,
            'widgets' => $this->renderedWidgets,
        ];
    }
}
