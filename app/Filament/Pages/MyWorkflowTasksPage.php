<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domains\Workflow\Actions\ClaimUserTaskAction;
use App\Domains\Workflow\Actions\CompleteUserTaskAction;
use App\Domains\Workflow\Enums\UserTaskStatus;
use App\Domains\Workflow\Models\UserTask;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables;

class MyWorkflowTasksPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;
    protected static ?string $navigationGroup = 'گردش کار';
    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.my-workflow-tasks';

    public static function getNavigationLabel(): string
    {
        return 'وظایف من (Workflow)';
    }

    public function getTitle(): string
    {
        return 'وظایف من در فرایندها';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                UserTask::query()
                    ->forUser(auth()->user())
                    ->open(),
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('عنوان')->limit(50)->searchable(),
                Tables\Columns\TextColumn::make('instance.process_key')->label('فرایند')->badge(),
                Tables\Columns\TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge(),
                Tables\Columns\TextColumn::make('priority')->label('اولویت')->badge(),
                Tables\Columns\TextColumn::make('due_at')
                    ->label('مهلت')
                    ->dateTime('Y/m/d H:i')
                    ->color(fn ($state) => $state && $state->isPast() ? 'danger' : 'gray')
                    ->placeholder('—'),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('claim')
                        ->label('Claim')
                        ->icon(Heroicon::OutlinedHandRaised)
                        ->color('warning')
                        ->visible(fn (UserTask $r) => $r->canBeClaimedBy(auth()->user())
                            && $r->status !== UserTaskStatus::Claimed)
                        ->action(function (UserTask $r) {
                            try {
                                app(ClaimUserTaskAction::class)->execute($r, auth()->user());
                                Notification::make()->title('Claim شد')->success()->send();
                            } catch (\Throwable $e) {
                                Notification::make()->title('خطا')->body($e->getMessage())->danger()->send();
                            }
                        }),

                    Action::make('complete')
                        ->label('تکمیل')
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->color('success')
                        ->visible(fn (UserTask $r) => $r->canBeCompletedBy(auth()->user()))
                        ->schema([
                            Select::make('outcome')->label('نتیجه')->options([
                                'approve' => 'تأیید',
                                'reject' => 'رد',
                                'forward' => 'ارجاع',
                            ]),
                            Textarea::make('comment')->label('نظر')->rows(3),
                        ])
                        ->action(function (UserTask $r, array $data) {
                            try {
                                app(CompleteUserTaskAction::class)->execute(
                                    $r,
                                    auth()->user(),
                                    formData: ['comment' => $data['comment'] ?? null],
                                    outcome: $data['outcome'] ?? null,
                                    comment: $data['comment'] ?? null,
                                );
                                Notification::make()->title('تکمیل شد')->success()->send();
                            } catch (\Throwable $e) {
                                Notification::make()->title('خطا')->body($e->getMessage())->danger()->send();
                            }
                        }),
                ]),
            ])
            ->defaultSort('priority', 'desc');
    }
}
