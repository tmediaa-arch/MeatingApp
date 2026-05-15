<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domains\VideoConference\Actions\CheckProviderHealthAction;
use App\Domains\VideoConference\Enums\HealthStatus;
use App\Domains\VideoConference\Enums\VideoConferenceDriver;
use App\Domains\VideoConference\Models\VideoConferenceProvider;
use App\Filament\Admin\Schemas\FormLayout;
use App\Filament\Resources\VideoConferenceProviderResource\Pages;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
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

class VideoConferenceProviderResource extends Resource
{
    protected static ?string $model = VideoConferenceProvider::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedVideoCamera;
    protected static string|\UnitEnum|null $navigationGroup = 'ویدئوکنفرانس';
    protected static ?int $navigationSort = 5;
    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return 'Provider ویدئوکنفرانس';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Providerهای ویدئوکنفرانس';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(FormLayout::withSidebar(
            main: [
                Section::make('اطلاعات پایه')
                    ->columns(2)
                    ->schema([
                        Select::make('organization_id')
                            ->label('سازمان')
                            ->relationship('organization', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),

                        TextInput::make('name')
                            ->label('نام')
                            ->required()
                            ->maxLength(255),

                        Select::make('driver')
                            ->label('Driver')
                            ->options(VideoConferenceDriver::class)
                            ->required()
                            ->live(),
                    ]),

                Section::make('پیکربندی Driver')
                    ->description('تنظیمات هر driver متفاوت است. این مقادیر به‌صورت encrypted ذخیره می‌شوند.')
                    ->columns(2)
                    ->visible(fn ($get) => filled($get('driver')))
                    ->schema(function ($get) {
                        return self::driverConfigFields($get('driver'));
                    }),

                Section::make('قابلیت‌ها و محدودیت‌ها')
                    ->columns(2)
                    ->collapsible()
                    ->schema([
                        TextInput::make('max_concurrent_meetings')
                            ->label('حداکثر جلسات همزمان')
                            ->numeric()
                            ->helperText('خالی = نامحدود'),

                        TextInput::make('max_participants_per_meeting')
                            ->label('حداکثر شرکت‌کنندگان هر جلسه')
                            ->numeric(),

                        Toggle::make('supports_recording')->label('پشتیبانی از ضبط'),
                        Toggle::make('supports_streaming')->label('پشتیبانی از پخش زنده'),
                        Toggle::make('supports_breakout_rooms')->label('Breakout Rooms'),
                    ]),
            ],
            sidebar: [
                Section::make('وضعیت')
                    ->schema([
                        Toggle::make('is_active')
                            ->label('فعال')
                            ->default(true),

                        Toggle::make('is_default')
                            ->label('پیش‌فرض سازمان')
                            ->helperText('در صورت true، انتخاب پیش‌فرض هنگام ایجاد اتاق است.'),
                    ]),
            ],
        ));
    }

    private static function driverConfigFields(string|VideoConferenceDriver|null $driver): array
    {
        $driver = $driver instanceof VideoConferenceDriver ? $driver->value : $driver;

        return match ($driver) {
            'alocom' => [
                TextInput::make('config.api_base_url')
                    ->label('API Base URL')
                    ->required()
                    ->url(),
                TextInput::make('config.api_token')
                    ->label('API Token')
                    ->required()
                    ->password()
                    ->revealable(),
                TextInput::make('config.tenant_id')
                    ->label('Tenant ID')
                    ->required(),
            ],
            'jitsi' => [
                TextInput::make('config.base_url')
                    ->label('Base URL')
                    ->required()
                    ->url(),
                TextInput::make('config.jwt_secret')
                    ->label('JWT Secret')
                    ->required()
                    ->password()
                    ->revealable(),
                TextInput::make('config.jwt_app_id')
                    ->label('JWT App ID')
                    ->required(),
                TextInput::make('config.management_url')
                    ->label('Management URL (اختیاری)')
                    ->helperText('برای استفاده از Jibri recording'),
                TextInput::make('config.management_token')
                    ->label('Management Token (اختیاری)')
                    ->password()
                    ->revealable(),
            ],
            'bigbluebutton' => [
                TextInput::make('config.base_url')
                    ->label('Base URL')
                    ->required()
                    ->url(),
                TextInput::make('config.shared_secret')
                    ->label('Shared Secret')
                    ->required()
                    ->password()
                    ->revealable(),
                TextInput::make('config.logout_url')
                    ->label('Logout URL')
                    ->url(),
            ],
            'null' => [
                TextInput::make('config.manual_host_url')
                    ->label('Host URL پیش‌فرض')
                    ->url()
                    ->helperText('اگر تنظیم نشود، یک URL مصنوعی تولید می‌شود'),
                TextInput::make('config.manual_attendee_url')
                    ->label('Attendee URL پیش‌فرض')
                    ->url(),
            ],
            default => [],
        };
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('نام')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('driver')
                    ->label('Driver')
                    ->badge(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('فعال')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_default')
                    ->label('پیش‌فرض')
                    ->boolean(),

                Tables\Columns\TextColumn::make('health_status')
                    ->label('سلامت')
                    ->badge(),

                Tables\Columns\TextColumn::make('active_rooms_count')
                    ->label('اتاق‌های فعال')
                    ->counts('activeRooms'),

                Tables\Columns\TextColumn::make('max_concurrent_meetings')
                    ->label('حد همزمانی')
                    ->placeholder('نامحدود'),

                Tables\Columns\TextColumn::make('last_health_check_at')
                    ->label('آخرین بررسی')
                    ->since()
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('فعال'),
                SelectFilter::make('driver')
                    ->label('Driver')
                    ->options(VideoConferenceDriver::class),
                SelectFilter::make('health_status')
                    ->label('سلامت')
                    ->options(HealthStatus::class),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),

                    Action::make('check_health')
                        ->label('بررسی سلامت')
                        ->icon(Heroicon::OutlinedHeart)
                        ->color('info')
                        ->action(function (VideoConferenceProvider $provider) {
                            $result = app(CheckProviderHealthAction::class)->execute($provider);
                            Notification::make()
                                ->title("سلامت: {$result->health_status->label()}")
                                ->body($result->health_message ?? '')
                                ->{$result->health_status->isUsable() ? 'success' : 'danger'}()
                                ->send();
                        }),

                    Action::make('set_default')
                        ->label('پیش‌فرض کن')
                        ->icon(Heroicon::OutlinedStar)
                        ->color('warning')
                        ->visible(fn (VideoConferenceProvider $p) => !$p->is_default)
                        ->requiresConfirmation()
                        ->action(function (VideoConferenceProvider $provider) {
                            \DB::transaction(function () use ($provider) {
                                VideoConferenceProvider::where('organization_id', $provider->organization_id)
                                    ->update(['is_default' => false]);
                                $provider->update(['is_default' => true]);
                            });
                            Notification::make()->title('به‌عنوان پیش‌فرض ثبت شد')->success()->send();
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVideoConferenceProviders::route('/'),
            'create' => Pages\CreateVideoConferenceProvider::route('/create'),
            'edit' => Pages\EditVideoConferenceProvider::route('/{record}/edit'),
        ];
    }
}
