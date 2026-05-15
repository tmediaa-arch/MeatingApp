<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domains\Workflow\Enums\UserTaskStatus;
use App\Domains\Workflow\Models\UserTask;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MyPendingWorkflowTasksWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 52;

    protected function getStats(): array
    {
        $user = auth()->user();
        if (!$user) return [];

        $open = UserTask::query()->forUser($user)->open()->count();
        $overdue = UserTask::query()->forUser($user)->overdue()->count();
        $assigned = UserTask::query()
            ->where('assignee_user_id', $user->id)
            ->where('status', UserTaskStatus::Assigned)
            ->count();

        return [
            Stat::make('UserTaskهای من', $open)
                ->description($assigned > 0 ? "{$assigned} منتظر claim" : '—')
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                ->color($overdue > 0 ? 'danger' : ($open > 0 ? 'warning' : 'success'))
                ->url(route('filament.admin.pages.my-workflow-tasks')),
        ];
    }
}
