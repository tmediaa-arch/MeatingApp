<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domains\ServiceRequests\Actions\ApproveServiceRequestAction;
use App\Domains\ServiceRequests\Actions\CompleteServiceRequestAction;
use App\Domains\ServiceRequests\Actions\RejectServiceRequestAction;
use App\Domains\ServiceRequests\Actions\SubmitServiceRequestAction;
use App\Domains\ServiceRequests\Enums\ServiceRequestStatus;
use App\Domains\ServiceRequests\Enums\ServiceRequestType;
use App\Domains\ServiceRequests\Models\ServiceRequest;
use App\Filament\Admin\Schemas\FormLayout;
use App\Filament\Resources\ServiceRequestResource\Pages;
use Filament\Forms\Components\DateTimePicker;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ServiceRequestResource extends Resource
{
    protected static ?string $model = ServiceRequest::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;
    protected static string|\UnitEnum|null $navigationGroup = 'درخواست‌های جانبی';
    protected static ?int $navigationSort = 10;
    protected static ?string $recordTitleAttribute = 'title';

    public static function getModelLabel(): string
    {
        return 'درخواست جانبی';
    }

    public static function getPluralModelLabel(): string
    {
        return 'درخواست‌های جانبی';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(FormLayout::withSidebar(
            main: [
                Section::make('مشخصات درخواست')
                    ->columns(2)
                    ->schema([
                        Select::make('type')
                            ->label('نوع')
                            ->options(ServiceRequestType::class)
                            ->required()
                            ->live(),

                        TextInput::make('title')
                            ->label('عنوان')
                            ->required()
                            ->maxLength(255),

                        Textarea::make('description')
                            ->label('شرح')
                            ->rows(3)
                            ->columnSpanFull(),

                        DateTimePicker::make('required_at')
                            ->label('زمان مورد نیاز')
                            ->required()
                            ->minDate(now()),

                        TextInput::make('estimated_duration_minutes')
                            ->label('مدت تخمینی (دقیقه)')
                            ->numeric(),
                    ]),

                Section::make('اطلاعات اختصاصی نوع')
                    ->columns(2)
                    ->schema(function ($get) {
                        $type = ServiceRequestType::tryFrom($get('type') ?? '');
                        if (! $type) {
                            return [];
                        }

                        return collect($type->typeSpecificFields())
                            ->map(fn ($label, $key) => TextInput::make("type_specific_data.{$key}")
                                ->label($label))
                            ->values()
                            ->all();
                    })
                    ->visible(fn ($get) => filled($get('type'))),

                Section::make('ارتباط با جلسه و واحد')
                    ->columns(2)
                    ->collapsible()
                    ->schema([
                        Select::make('meeting_id')
                            ->label('جلسه مرتبط')
                            ->relationship('meeting', 'subject')
                            ->searchable()
                            ->preload(),

                        Select::make('provider_unit_id')
                            ->label('واحد ارائه‌دهنده')
                            ->relationship('providerUnit', 'name')
                            ->searchable()
                            ->preload(),

                        TextInput::make('estimated_cost')
                            ->label('هزینه تخمینی')
                            ->numeric()
                            ->prefix('﷼'),
                    ]),
            ],
            sidebar: [
                Section::make('اولویت')
                    ->schema([
                        Select::make('priority')
                            ->label('اولویت')
                            ->options([
                                'low' => 'پایین',
                                'normal' => 'عادی',
                                'high' => 'بالا',
                                'critical' => 'بحرانی',
                            ])
                            ->default('normal'),
                    ]),
            ],
        ));
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
                    ->badge(),

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
                    ->badge(),

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
                SelectFilter::make('type')
                    ->label('نوع')
                    ->options(ServiceRequestType::class),

                SelectFilter::make('status')
                    ->label('وضعیت')
                    ->options(ServiceRequestStatus::class),

                Filter::make('mine')
                    ->label('فقط درخواست‌های من')
                    ->query(fn (Builder $q) => $q->where('requester_user_id', auth()->id())),

                Filter::make('overdue')
                    ->label('فقط overdue')
                    ->query(fn (Builder $q) => $q->overdue()),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make()
                        ->visible(fn (ServiceRequest $r) => $r->status === ServiceRequestStatus::Draft
                            && $r->requester_user_id === auth()->id()),

                    Action::make('submit')
                        ->label('ارسال برای بررسی')
                        ->icon(Heroicon::OutlinedPaperAirplane)
                        ->color('success')
                        ->visible(fn (ServiceRequest $r) => $r->status === ServiceRequestStatus::Draft)
                        ->requiresConfirmation()
                        ->action(function (ServiceRequest $r) {
                            app(SubmitServiceRequestAction::class)->execute($r, auth()->user());
                            Notification::make()->title('درخواست ارسال شد')->success()->send();
                        }),

                    Action::make('approve')
                        ->label('تأیید')
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->color('success')
                        ->visible(fn (ServiceRequest $r) => in_array($r->status, [
                            ServiceRequestStatus::Submitted,
                            ServiceRequestStatus::UnderReview,
                        ], true))
                        ->schema([
                            Textarea::make('comment')->label('یادداشت')->rows(2),
                        ])
                        ->action(function (ServiceRequest $r, array $data) {
                            app(ApproveServiceRequestAction::class)->execute($r, auth()->user(), $data['comment'] ?? null);
                            Notification::make()->title('تأیید شد')->success()->send();
                        }),

                    Action::make('reject')
                        ->label('رد')
                        ->icon(Heroicon::OutlinedXCircle)
                        ->color('danger')
                        ->visible(fn (ServiceRequest $r) => in_array($r->status, [
                            ServiceRequestStatus::Submitted,
                            ServiceRequestStatus::UnderReview,
                        ], true))
                        ->schema([
                            Textarea::make('reason')->label('دلیل رد')->required()->rows(2),
                        ])
                        ->action(function (ServiceRequest $r, array $data) {
                            app(RejectServiceRequestAction::class)->execute($r, auth()->user(), $data['reason']);
                            Notification::make()->title('رد شد')->warning()->send();
                        }),

                    Action::make('complete')
                        ->label('تکمیل')
                        ->icon(Heroicon::OutlinedFlag)
                        ->color('success')
                        ->visible(fn (ServiceRequest $r) => $r->status === ServiceRequestStatus::InProgress)
                        ->schema([
                            TextInput::make('actual_cost')->label('هزینه واقعی')->numeric()->prefix('﷼'),
                            Textarea::make('comment')->label('یادداشت تکمیل')->rows(2),
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
                ]),
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
