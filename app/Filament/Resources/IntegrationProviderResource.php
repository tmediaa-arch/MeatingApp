<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domains\Integrations\Actions\TestProviderConnectionAction;
use App\Domains\Integrations\Enums\IntegrationHealthStatus;
use App\Domains\Integrations\Enums\IntegrationType;
use App\Domains\Integrations\Jobs\RunIntegrationSyncJob;
use App\Domains\Integrations\Models\IntegrationProvider;
use App\Filament\Admin\Schemas\FormLayout;
use App\Filament\Resources\IntegrationProviderResource\Pages;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class IntegrationProviderResource extends Resource
{
    protected static ?string $model = IntegrationProvider::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPathRoundedSquare;
    protected static string|\UnitEnum|null $navigationGroup = 'یکپارچه‌سازی';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'display_name';

    public static function getModelLabel(): string
    {
        return 'Provider یکپارچه‌سازی';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Providerهای یکپارچه‌سازی';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(FormLayout::withSidebar(
            main: [
                Section::make('اطلاعات کلی')
                    ->columns(2)
                    ->schema([
                        TextInput::make('display_name')
                            ->label('نام نمایشی')->required()->maxLength(200),
                        TextInput::make('key')
                            ->label('کلید')->required()->maxLength(100)
                            ->helperText('یکتا، snake_case (مثل primary_ldap)'),
                        Select::make('type')
                            ->label('نوع')
                            ->options(IntegrationType::class)
                            ->live()
                            ->required(),
                        Select::make('driver')
                            ->label('Driver')
                            ->required()
                            ->options(function ($get) {
                                $type = $get('type');
                                $drivers = config("integrations.drivers.{$type}", []);
                                return collect(array_keys($drivers))->mapWithKeys(fn ($d) => [$d => $d]);
                            }),
                    ]),

                Section::make('تنظیمات اتصال')
                    ->description('پیکربندی JSON specific به نوع provider')
                    ->collapsible()
                    ->schema([
                        KeyValue::make('config')
                            ->label('پارامترها')
                            ->keyLabel('کلید')->valueLabel('مقدار')
                            ->columnSpanFull(),
                    ]),

                Section::make('Sync خودکار')
                    ->columns(2)
                    ->visible(fn ($get) => in_array($get('type'), ['ldap', 'hrs']))
                    ->schema([
                        Toggle::make('auto_sync_enabled')->label('فعال‌سازی sync خودکار'),
                        TextInput::make('sync_schedule')
                            ->label('Cron Expression')->maxLength(100)
                            ->helperText('مثال: 0 2 * * * — هر روز ۲ بامداد'),
                    ]),
            ],
            sidebar: [
                Section::make('وضعیت')
                    ->schema([
                        Toggle::make('is_active')->label('فعال')->default(false),
                    ]),
            ],
        ));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('display_name')
                    ->label('نام')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('key')->label('کلید')->fontFamily('mono'),
                Tables\Columns\TextColumn::make('type')
                    ->label('نوع')->badge(),
                Tables\Columns\TextColumn::make('driver')->label('Driver')->fontFamily('mono')->size('sm'),
                Tables\Columns\TextColumn::make('health_status')
                    ->label('سلامت')->badge(),
                Tables\Columns\IconColumn::make('is_active')->label('فعال')->boolean(),
                Tables\Columns\IconColumn::make('auto_sync_enabled')->label('Auto Sync')->boolean(),
                Tables\Columns\TextColumn::make('last_sync_at')->label('آخرین Sync')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(IntegrationType::class),
                TernaryFilter::make('is_active'),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    Action::make('test_connection')
                        ->label('تست اتصال')
                        ->icon(Heroicon::OutlinedBolt)
                        ->color('info')
                        ->action(function (IntegrationProvider $record) {
                            $result = app(TestProviderConnectionAction::class)->execute($record);
                            $notification = Notification::make()->title($result->message);
                            if ($result->status === IntegrationHealthStatus::Healthy) {
                                $notification->success()->body("Latency: {$result->latencyMs}ms");
                            } else {
                                $notification->danger();
                            }
                            $notification->send();
                        }),
                    Action::make('sync_now')
                        ->label('Sync فوری')
                        ->icon(Heroicon::OutlinedArrowPath)
                        ->color('warning')
                        ->visible(fn (IntegrationProvider $record) => $record->type?->supportsSync())
                        ->requiresConfirmation()
                        ->action(function (IntegrationProvider $record) {
                            RunIntegrationSyncJob::dispatch($record->id, 'manual', auth()->id());
                            Notification::make()
                                ->title('Sync در صف اجرا قرار گرفت')
                                ->success()->send();
                        }),
                ]),
            ])
            ->defaultSort('type');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListIntegrationProviders::route('/'),
            'create' => Pages\CreateIntegrationProvider::route('/create'),
            'edit' => Pages\EditIntegrationProvider::route('/{record}/edit'),
        ];
    }
}
