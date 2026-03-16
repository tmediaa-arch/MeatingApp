<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domains\ServiceRequests\Actions\ApproveServiceRequestAction;
use App\Domains\ServiceRequests\Actions\AssignServiceRequestAction;
use App\Domains\ServiceRequests\Actions\CompleteServiceRequestAction;
use App\Domains\ServiceRequests\Actions\RejectServiceRequestAction;
use App\Domains\ServiceRequests\Actions\SubmitServiceRequestAction;
use App\Domains\ServiceRequests\Enums\ServiceRequestStatus;
use App\Domains\ServiceRequests\Enums\ServiceRequestType;
use App\Domains\ServiceRequests\Models\ServiceRequest;
use App\Filament\Resources\ServiceRequestResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ServiceRequestResource extends Resource
{
    protected static ?string $model = ServiceRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'درخواست‌های جانبی';
    protected static ?int $navigationSort = 10;

    public static function getModelLabel(): string
    {
        return 'درخواست جانبی';
    }

    public static function getPluralModelLabel(): string
    {
        return 'درخواست‌های جانبی';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('مشخصات درخواست')->schema([
                Forms\Components\Select::make('type')
                    ->label('نوع')
                    ->options(ServiceRequestType::options())
                    ->required()
                    ->reactive(),

                Forms\Components\TextInput::make('title')
                    ->label('عنوان')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Textarea::make('description')
                    ->label('شرح')
                    ->rows(3)
                    ->columnSpanFull(),

                Forms\Components\Select::make('priority')
                    ->label('اولویت')
                    ->options([
                        'low' => 'پایین',
                        'normal' => 'عادی',
                        'high' => 'بالا',
                        'critical' => 'بحرانی',
                    ])
                    ->default('normal'),

                Forms\Components\DateTimePicker::make('required_at')
                    ->label('زمان مورد نیاز')
                    ->required()
                    ->minDate(now()),

                Forms\Components\TextInput::make('estimated_duration_minutes')
                    ->label('مدت تخمینی (دقیقه)')
                    ->numeric(),
            ])->columns(2),

            Forms\Components\Section::make('اطلاعات اختصاصی نوع')
                ->schema(function (Forms\Get $get) {
                    $type = ServiceRequestType::tryFrom($get('type') ?? '');
                    if (!$type) return [];

                    return collect($type->typeSpecificFields())
                        ->map(fn ($label, $key) => Forms\Components\TextInput::make("type_specific_data.{$key}")
                            ->label($label))
                        ->values()
                        ->all();
                })
                ->columns(2)
                ->visible(fn (Forms\Get $get) => filled($get('type'))),

            Forms\Components\Section::make('ارتباط با جلسه و واحد')->schema([
                Forms\Components\Select::make('meeting_id')
                    ->label('جلسه مرتبط')
                    ->relationship('meeting', 'subject')
                    ->searchable()
                    ->preload(),

                Forms\Components\Select::make('provider_unit_id')
                    ->label('واحد ارائه‌دهنده')
                    ->relationship('providerUnit', 'name')
                    ->searchable()
                    ->preload(),

                Forms\Components\TextInput::make('estimated_cost')
                    ->label('هزینه تخمینی')
                    ->numeric()
                    ->prefix('﷼'),
            ])->columns(2)->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('request_number')
                    ->label('شماره')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('نوع')
                    ->badge()
                    ->color(fn (ServiceRequestType $t) => $t->color())
                    ->formatStateUsing(fn (ServiceRequestType $t) => $t->label())
                    ->icon(fn (ServiceRequestType $t) => $t->icon()),

                Tables\Columns\TextColumn::make('title')
                    ->label('عنوان')
                    ->limit(40)
                    ->searchable(),

                Tables\Columns\TextColumn::make('priority')
                    ->label('اولویت')
                    ->badge()
                    ->color(fn ($s) => match ($s) {
                        'critical' => 'danger',
                        'high' => 'warning',
                        'normal' => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge()
                    ->color(fn (ServiceRequestStatus $s) => $s->color())
                    ->formatStateUsing(fn (ServiceRequestStatus $s) => $s->label()),

                Tables\Columns\TextColumn::make('required_at')
                    ->label('زمان نیاز')
                    ->dateTime('Y/m/d H:i')
                    ->sortable()
                    ->color(fn ($state) => $state && $state->isPast() ? 'danger' : 'gray'),

                Tables\Columns\TextColumn::make('requester.name')
                    ->label('درخواست‌کننده')
                    ->limit(20),

                Tables\Columns\TextColumn::make('assignedEmployee.full_name')
                    ->label('مجری')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('providerUnit.name')
                    ->label('واحد ارائه‌دهنده')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('estimated_cost')
                    ->label('هزینه تخمینی')
                    ->money('IRR', divideBy: 1)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('نوع')
                    ->options(ServiceRequestType::options()),

                Tables\Filters\SelectFilter::make('status')
                    ->label('وضعیت')
                    ->options(ServiceRequestStatus::options()),

                Tables\Filters\Filter::make('mine')
                    ->label('فقط درخواست‌های من')
                    ->query(fn (Builder $q) => $q->where('requester_user_id', auth()->id())),

                Tables\Filters\Filter::make('overdue')
                    ->label('فقط overdue')
                    ->query(fn (Builder $q) => $q->overdue()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (ServiceRequest $r) => $r->status === ServiceRequestStatus::Draft
                        && $r->requester_user_id === auth()->id()),

                Tables\Actions\Action::make('submit')
                    ->label('ارسال برای بررسی')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->visible(fn (ServiceRequest $r) => $r->status === ServiceRequestStatus::Draft)
                    ->requiresConfirmation()
                    ->action(function (ServiceRequest $r) {
                        app(SubmitServiceRequestAction::class)->execute($r, auth()->user());
                        Notification::make()->title('درخواست ارسال شد')->success()->send();
                    }),

                Tables\Actions\Action::make('approve')
                    ->label('تأیید')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (ServiceRequest $r) => in_array($r->status, [
                        ServiceRequestStatus::Submitted,
                        ServiceRequestStatus::UnderReview,
                    ], true))
                    ->form([
                        Forms\Components\Textarea::make('comment')->label('یادداشت')->rows(2),
                    ])
                    ->action(function (ServiceRequest $r, array $data) {
                        app(ApproveServiceRequestAction::class)->execute($r, auth()->user(), $data['comment'] ?? null);
                        Notification::make()->title('تأیید شد')->success()->send();
                    }),

                Tables\Actions\Action::make('reject')
                    ->label('رد')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (ServiceRequest $r) => in_array($r->status, [
                        ServiceRequestStatus::Submitted,
                        ServiceRequestStatus::UnderReview,
                    ], true))
                    ->form([
                        Forms\Components\Textarea::make('reason')->label('دلیل رد')->required()->rows(2),
                    ])
                    ->action(function (ServiceRequest $r, array $data) {
                        app(RejectServiceRequestAction::class)->execute($r, auth()->user(), $data['reason']);
                        Notification::make()->title('رد شد')->warning()->send();
                    }),

                Tables\Actions\Action::make('complete')
                    ->label('تکمیل')
                    ->icon('heroicon-o-flag')
                    ->color('success')
                    ->visible(fn (ServiceRequest $r) => $r->status === ServiceRequestStatus::InProgress)
                    ->form([
                        Forms\Components\TextInput::make('actual_cost')->label('هزینه واقعی')->numeric()->prefix('﷼'),
                        Forms\Components\Textarea::make('comment')->label('یادداشت تکمیل')->rows(2),
                    ])
                    ->action(function (ServiceRequest $r, array $data) {
                        app(CompleteServiceRequestAction::class)->execute(
                            $r,
                            auth()->user(),
                            $data['actual_cost'] ?? null,
                            $data['comment'] ?? null,
                        );
                        Notification::make()->title('تکمیل شد')->success()->send();
                    }),
            ])
            ->defaultSort('required_at', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServiceRequests::route('/'),
            'create' => Pages\CreateServiceRequest::route('/create'),
            'edit' => Pages\EditServiceRequest::route('/{record}/edit'),
            'view' => Pages\ViewServiceRequest::route('/{record}'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\ServiceRequestResource\RelationManagers\UpdatesRelationManager::class,
            \App\Filament\Resources\ServiceRequestResource\RelationManagers\AttachmentsRelationManager::class,
        ];
    }
}
