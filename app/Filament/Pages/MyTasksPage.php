<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domains\Tasks\Actions\UpdateTaskProgressAction;
use App\Domains\Tasks\Enums\TaskPriority;
use App\Domains\Tasks\Enums\TaskStatus;
use App\Domains\Tasks\Models\Task;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MyTasksPage extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = 'وظایف من';
    protected static ?string $title = 'وظایف من';
    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.pages.my-tasks';

    public ?string $activeTab = 'active';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('task_number')->label('شماره')->searchable(),
                Tables\Columns\TextColumn::make('title')->label('عنوان')->limit(40)->searchable(),
                Tables\Columns\TextColumn::make('priority')
                    ->label('اولویت')
                    ->badge()
                    ->color(fn (TaskPriority $p) => $p->color())
                    ->formatStateUsing(fn (TaskPriority $p) => $p->label()),
                Tables\Columns\TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge()
                    ->color(fn (TaskStatus $s) => $s->color())
                    ->formatStateUsing(fn (TaskStatus $s) => $s->label()),
                Tables\Columns\TextColumn::make('progress_percent')
                    ->label('پیشرفت')
                    ->state(fn ($r) => "{$r->progress_percent}%"),
                Tables\Columns\TextColumn::make('due_date')
                    ->label('مهلت')
                    ->date('Y/m/d')
                    ->color(fn ($state, $record) => $record->is_overdue ? 'danger' : null),
                Tables\Columns\TextColumn::make('my_role')
                    ->label('نقش من')
                    ->state(fn (Task $r) => $this->getUserRole($r))
                    ->badge(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('priority')->options(TaskPriority::options()),
                Tables\Filters\Filter::make('overdue')
                    ->label('فقط تأخیردار')
                    ->query(fn ($q) => $q->where('is_overdue', true)),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('باز کردن')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Task $r) => route('filament.admin.resources.tasks.view', $r)),

                Tables\Actions\Action::make('quickProgress')
                    ->label('پیشرفت')
                    ->icon('heroicon-o-chart-bar')
                    ->color('primary')
                    ->visible(fn (Task $r) => $r->assignee_user_id === auth()->id()
                        || $r->assignee_employee_id === auth()->user()->employee_id)
                    ->form([
                        Forms\Components\TextInput::make('progress_percent')
                            ->label('درصد پیشرفت جدید')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->required(),
                        Forms\Components\Textarea::make('comment')->label('توضیح')->rows(2),
                    ])
                    ->action(function (Task $r, array $data) {
                        app(UpdateTaskProgressAction::class)->execute(
                            $r,
                            (int) $data['progress_percent'],
                            $data['comment'] ?? null,
                        );
                        Notification::make()->success()->title('به‌روز شد')->send();
                    }),
            ])
            ->defaultSort('due_date');
    }

    protected function getTableQuery(): Builder
    {
        $query = Task::query()->forUser(auth()->user());

        return match ($this->activeTab) {
            'active' => $query->whereNotIn('status', [
                TaskStatus::Completed->value,
                TaskStatus::Cancelled->value,
            ]),
            'overdue' => $query->where('is_overdue', true)->open(),
            'completed' => $query->where('status', TaskStatus::Completed),
            default => $query,
        };
    }

    public function switchTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetTable();
    }

    private function getUserRole(Task $task): string
    {
        $user = auth()->user();
        if ($task->assignee_user_id === $user->id
            || $task->assignee_employee_id === $user->employee_id) return 'مجری';
        if ($task->supervisor_employee_id === $user->employee_id) return 'ناظر';
        if ($task->approver_employee_id === $user->employee_id) return 'تأییدکننده';
        if ($task->creator_user_id === $user->id) return 'ایجادکننده';
        return '—';
    }
}
