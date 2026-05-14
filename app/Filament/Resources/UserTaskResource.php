<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domains\Workflow\Actions\ClaimUserTaskAction;
use App\Domains\Workflow\Actions\CompleteUserTaskAction;
use App\Domains\Workflow\Enums\UserTaskStatus;
use App\Domains\Workflow\Models\UserTask;
use App\Filament\Resources\UserTaskResource\Pages;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UserTaskResource extends Resource
{
    protected static ?string $model = UserTask::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;
    protected static ?string $navigationGroup = 'گردش کار';
    protected static ?string $recordTitleAttribute = 'name';
    protected static ?int $navigationSort = 30;

    public static function getModelLabel(): string
    {
        return 'UserTask';
    }

    public static function getPluralModelLabel(): string
    {
        return 'UserTaskها';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('عنوان')
                    ->searchable()
                    ->limit(50),

                Tables\Columns\TextColumn::make('instance.process_key')
                    ->label('فرایند')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('instance.business_key')
                    ->label('کلید کسب‌وکار')
                    ->limit(20),

                Tables\Columns\TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge(),

                Tables\Columns\TextColumn::make('priority')->label('اولویت')->badge(),

                Tables\Columns\TextColumn::make('assignee.name')->label('مجری')->placeholder('—'),

                Tables\Columns\TextColumn::make('due_at')
                    ->label('مهلت')
                    ->dateTime('Y/m/d H:i')
                    ->color(fn ($state) => $state && $state->isPast() ? 'danger' : 'gray')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('outcome')->label('نتیجه')->placeholder('—'),

                Tables\Columns\TextColumn::make('completed_at')
                    ->label('تکمیل')
                    ->dateTime('Y/m/d H:i')
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('وضعیت')
                    ->options(UserTaskStatus::class),

                Filter::make('mine')
                    ->label('فقط مال من')
                    ->query(fn (Builder $q) => $q->forUser(auth()->user())),

                Filter::make('open')
                    ->label('فقط باز')
                    ->query(fn (Builder $q) => $q->open())
                    ->default(),

                Filter::make('overdue')
                    ->label('فقط overdue')
                    ->query(fn (Builder $q) => $q->overdue()),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    Action::make('claim')
                        ->label('Claim')
                        ->icon(Heroicon::OutlinedHandRaised)
                        ->color('warning')
                        ->visible(fn (UserTask $r) => $r->canBeClaimedBy(auth()->user()) && $r->status !== UserTaskStatus::Claimed)
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
                            Select::make('outcome')
                                ->label('نتیجه')
                                ->options([
                                    'approve' => 'تأیید',
                                    'reject' => 'رد',
                                    'forward' => 'ارجاع',
                                    'request_info' => 'درخواست اطلاعات بیشتر',
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
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUserTasks::route('/'),
            'view' => Pages\ViewUserTask::route('/{record}'),
        ];
    }
}
