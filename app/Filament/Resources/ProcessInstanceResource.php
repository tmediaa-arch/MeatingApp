<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domains\Workflow\Actions\CancelInstanceAction;
use App\Domains\Workflow\Actions\ResumeInstanceAction;
use App\Domains\Workflow\Actions\SuspendInstanceAction;
use App\Domains\Workflow\Enums\ProcessInstanceStatus;
use App\Domains\Workflow\Models\ProcessInstance;
use App\Filament\Resources\ProcessInstanceResource\Pages;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProcessInstanceResource extends Resource
{
    protected static ?string $model = ProcessInstance::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedPlayCircle;
    protected static ?string $navigationGroup = 'گردش کار';
    protected static ?int $navigationSort = 20;
    protected static ?string $recordTitleAttribute = 'instance_uuid';

    public static function getModelLabel(): string
    {
        return 'Instance';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Instanceها';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('instance_uuid')
                    ->label('UUID')
                    ->limit(8)
                    ->copyable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('process_key')
                    ->label('فرایند')
                    ->badge()
                    ->color('gray')
                    ->searchable(),

                Tables\Columns\TextColumn::make('process_version')
                    ->label('v')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('business_key')
                    ->label('کلید کسب‌وکار')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge(),

                Tables\Columns\TextColumn::make('priority')
                    ->label('اولویت')
                    ->badge(),

                Tables\Columns\TextColumn::make('started_at')
                    ->label('شروع')
                    ->dateTime('Y/m/d H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('sla_due_at')
                    ->label('SLA')
                    ->dateTime('Y/m/d H:i')
                    ->color(fn ($state) => $state && $state->isPast() ? 'danger' : 'gray')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('active_tokens_count')
                    ->label('Tokens')
                    ->counts('activeTokens')
                    ->badge(),

                Tables\Columns\TextColumn::make('active_user_tasks_count')
                    ->label('UserTasks')
                    ->counts('activeUserTasks')
                    ->badge()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('open_incidents_count')
                    ->label('Incidents')
                    ->counts('openIncidents')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('وضعیت')
                    ->options(ProcessInstanceStatus::class),

                SelectFilter::make('process_key')
                    ->label('فرایند')
                    ->options(fn () => \App\Domains\Workflow\Models\ProcessDefinition::query()
                        ->select('process_key')
                        ->distinct()
                        ->pluck('process_key', 'process_key')
                        ->toArray()),

                Filter::make('sla_breached')
                    ->label('فقط SLA رد شده')
                    ->query(fn ($q) => $q->slaBreached()),

                Filter::make('with_incidents')
                    ->label('فقط با Incident')
                    ->query(fn ($q) => $q->has('openIncidents')),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),

                    Action::make('suspend')
                        ->label('توقف')
                        ->icon(Heroicon::OutlinedPauseCircle)
                        ->color('warning')
                        ->visible(fn (ProcessInstance $r) => $r->status === ProcessInstanceStatus::Running)
                        ->requiresConfirmation()
                        ->schema([
                            Textarea::make('reason')->label('دلیل')->rows(2),
                        ])
                        ->action(function (ProcessInstance $r, array $data) {
                            try {
                                app(SuspendInstanceAction::class)->execute($r, auth()->user(), $data['reason'] ?? null);
                                Notification::make()->title('instance متوقف شد')->success()->send();
                            } catch (\Throwable $e) {
                                Notification::make()->title('خطا')->body($e->getMessage())->danger()->send();
                            }
                        }),

                    Action::make('resume')
                        ->label('ادامه')
                        ->icon(Heroicon::OutlinedPlayCircle)
                        ->color('success')
                        ->visible(fn (ProcessInstance $r) => $r->status === ProcessInstanceStatus::Suspended)
                        ->requiresConfirmation()
                        ->action(function (ProcessInstance $r) {
                            try {
                                app(ResumeInstanceAction::class)->execute($r, auth()->user());
                                Notification::make()->title('instance ادامه یافت')->success()->send();
                            } catch (\Throwable $e) {
                                Notification::make()->title('خطا')->body($e->getMessage())->danger()->send();
                            }
                        }),

                    Action::make('cancel')
                        ->label('لغو')
                        ->icon(Heroicon::OutlinedXCircle)
                        ->color('danger')
                        ->visible(fn (ProcessInstance $r) => !$r->status->isTerminal())
                        ->requiresConfirmation()
                        ->schema([
                            Textarea::make('reason')->label('دلیل لغو')->required()->rows(2),
                        ])
                        ->action(function (ProcessInstance $r, array $data) {
                            try {
                                app(CancelInstanceAction::class)->execute($r, auth()->user(), $data['reason']);
                                Notification::make()->title('instance لغو شد')->success()->send();
                            } catch (\Throwable $e) {
                                Notification::make()->title('خطا')->body($e->getMessage())->danger()->send();
                            }
                        }),
                ]),
            ])
            ->defaultSort('started_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProcessInstances::route('/'),
            'view' => Pages\ViewProcessInstance::route('/{record}'),
        ];
    }
}
