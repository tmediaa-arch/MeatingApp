<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domains\Workflow\Enums\ProcessInstanceStatus;
use App\Domains\Workflow\Models\ProcessIncident;
use App\Domains\Workflow\Models\ProcessInstance;
use App\Domains\Workflow\Models\UserTask;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class WorkflowMonitorPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;
    protected static string|\UnitEnum|null $navigationGroup = 'گردش کار';
    protected static ?int $navigationSort = 100;

    protected string $view = 'filament.pages.workflow-monitor';

    public static function getNavigationLabel(): string
    {
        return 'مانیتورینگ Workflow';
    }

    public function getTitle(): string
    {
        return 'داشبورد مانیتورینگ Workflow';
    }

    protected function getViewData(): array
    {
        return [
            'running' => ProcessInstance::where('status', ProcessInstanceStatus::Running)->count(),
            'suspended' => ProcessInstance::where('status', ProcessInstanceStatus::Suspended)->count(),
            'completed_today' => ProcessInstance::where('status', ProcessInstanceStatus::Completed)
                ->where('completed_at', '>=', now()->startOfDay())
                ->count(),
            'failed_today' => ProcessInstance::where('status', ProcessInstanceStatus::Failed)
                ->where('updated_at', '>=', now()->startOfDay())
                ->count(),
            'sla_breached' => ProcessInstance::slaBreached()->count(),
            'open_incidents' => ProcessIncident::where('status', 'open')->count(),
            'open_user_tasks' => UserTask::open()->count(),
            'overdue_user_tasks' => UserTask::overdue()->count(),
            'recent_incidents' => ProcessIncident::with('instance')
                ->where('status', 'open')
                ->latest()
                ->limit(10)
                ->get(),
        ];
    }
}
