<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domains\Integrations\Actions\TestProviderConnectionAction;
use App\Domains\Integrations\Enums\IntegrationHealthStatus;
use App\Domains\Integrations\Enums\IntegrationType;
use App\Domains\Integrations\Jobs\RunIntegrationSyncJob;
use App\Domains\Integrations\Models\IntegrationProvider;
use App\Filament\Resources\IntegrationProviderResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class IntegrationProviderResource extends Resource
{
    protected static ?string $model = IntegrationProvider::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path-rounded-square';
    protected static ?string $navigationGroup = 'یکپارچه‌سازی';
    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return 'Provider یکپارچه‌سازی';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Providerهای یکپارچه‌سازی';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('اطلاعات کلی')
                ->schema([
                    Forms\Components\TextInput::make('display_name')
                        ->label('نام نمایشی')->required()->maxLength(200),
                    Forms\Components\TextInput::make('key')
                        ->label('کلید')->required()->maxLength(100)
                        ->helperText('یکتا، snake_case (مثل primary_ldap)'),
                    Forms\Components\Select::make('type')
                        ->label('نوع')
                        ->options(collect(IntegrationType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()]))
                        ->reactive()
                        ->required(),
                    Forms\Components\Select::make('driver')
                        ->label('Driver')
                        ->required()
                        ->options(function (Forms\Get $get) {
                            $type = $get('type');
                            $drivers = config("integrations.drivers.{$type}", []);
                            return collect(array_keys($drivers))->mapWithKeys(fn ($d) => [$d => $d]);
                        }),
                    Forms\Components\Toggle::make('is_active')->label('فعال')->default(false),
                ])->columns(2),

            Forms\Components\Section::make('تنظیمات اتصال')
                ->description('پیکربندی JSON specific به نوع provider')
                ->schema([
                    Forms\Components\KeyValue::make('config')
                        ->label('پارامترها')
                        ->keyLabel('کلید')->valueLabel('مقدار')
                        ->columnSpanFull(),
                ])->collapsible(),

            Forms\Components\Section::make('Sync خودکار')
                ->visible(fn (Forms\Get $get) => in_array($get('type'), ['ldap', 'hrs']))
                ->schema([
                    Forms\Components\Toggle::make('auto_sync_enabled')->label('فعال‌سازی sync خودکار'),
                    Forms\Components\TextInput::make('sync_schedule')
                        ->label('Cron Expression')->maxLength(100)
                        ->helperText('مثال: 0 2 * * * — هر روز ۲ بامداد'),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('display_name')
                    ->label('نام')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('key')->label('کلید')->fontFamily('mono'),
                Tables\Columns\TextColumn::make('type')
                    ->label('نوع')->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof IntegrationType ? $state->label() : $state),
                Tables\Columns\TextColumn::make('driver')->label('Driver')->fontFamily('mono')->size('sm'),
                Tables\Columns\TextColumn::make('health_status')
                    ->label('سلامت')->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof IntegrationHealthStatus ? $state->label() : $state)
                    ->color(fn ($state) => $state instanceof IntegrationHealthStatus ? $state->color() : 'gray'),
                Tables\Columns\IconColumn::make('is_active')->label('فعال')->boolean(),
                Tables\Columns\IconColumn::make('auto_sync_enabled')->label('Auto Sync')->boolean(),
                Tables\Columns\TextColumn::make('last_sync_at')->label('آخرین Sync')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options(collect(IntegrationType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()])),
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Tables\Actions\Action::make('test_connection')
                    ->label('تست اتصال')
                    ->icon('heroicon-o-bolt')
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
                Tables\Actions\Action::make('sync_now')
                    ->label('Sync فوری')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (IntegrationProvider $record) => $record->type?->supportsSync())
                    ->requiresConfirmation()
                    ->action(function (IntegrationProvider $record) {
                        RunIntegrationSyncJob::dispatch($record->id, 'manual', auth()->id());
                        Notification::make()
                            ->title('Sync در صف اجرا قرار گرفت')
                            ->success()->send();
                    }),
                Tables\Actions\EditAction::make(),
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
