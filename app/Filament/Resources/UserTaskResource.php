<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domains\Workflow\Actions\ClaimUserTaskAction;
use App\Domains\Workflow\Actions\CompleteUserTaskAction;
use App\Domains\Workflow\Enums\UserTaskStatus;
use App\Domains\Workflow\Models\UserTask;
use App\Filament\Resources\UserTaskResource\Pages;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UserTaskResource extends Resource
{
    protected static ?string $model = UserTask::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationGroup = 'گردش کار';
    protected static ?int $navigationSort = 30;

    public static function getModelLabel(): string
    {
        return 'UserTask';
    }

    public static function getPluralModelLabel(): string
    {
        return 'UserTaskها';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
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
                    ->badge()
                    ->color(fn (UserTaskStatus $s) => $s->color())
                    ->formatStateUsing(fn (UserTaskStatus $s) => $s->label()),

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
                Tables\Filters\SelectFilter::make('status')
                    ->label('وضعیت')
                    ->options(collect(UserTaskStatus::cases())
                        ->mapWithKeys(fn ($s) => [$s->value => $s->label()])
                        ->toArray()),

                Tables\Filters\Filter::make('mine')
                    ->label('فقط مال من')
                    ->query(fn (Builder $q) => $q->forUser(auth()->user())),

                Tables\Filters\Filter::make('open')
                    ->label('فقط باز')
                    ->query(fn (Builder $q) => $q->open())
                    ->default(),

                Tables\Filters\Filter::make('overdue')
                    ->label('فقط overdue')
                    ->query(fn (Builder $q) => $q->overdue()),
            ])
            ->actions([
                Tables\Actions\Action::make('claim')
                    ->label('Claim')
                    ->icon('heroicon-o-hand-raised')
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

                Tables\Actions\Action::make('complete')
                    ->label('تکمیل')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (UserTask $r) => $r->canBeCompletedBy(auth()->user()))
                    ->form([
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

                Tables\Actions\ViewAction::make(),
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
