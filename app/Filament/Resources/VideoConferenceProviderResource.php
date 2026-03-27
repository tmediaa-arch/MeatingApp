<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domains\VideoConference\Actions\CheckProviderHealthAction;
use App\Domains\VideoConference\Enums\HealthStatus;
use App\Domains\VideoConference\Enums\VideoConferenceDriver;
use App\Domains\VideoConference\Models\VideoConferenceProvider;
use App\Domains\VideoConference\Services\VideoConferenceProviderManager;
use App\Filament\Resources\VideoConferenceProviderResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VideoConferenceProviderResource extends Resource
{
    protected static ?string $model = VideoConferenceProvider::class;

    protected static ?string $navigationIcon = 'heroicon-o-video-camera';
    protected static ?string $navigationGroup = 'ویدئوکنفرانس';
    protected static ?int $navigationSort = 5;

    public static function getModelLabel(): string
    {
        return 'Provider ویدئوکنفرانس';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Providerهای ویدئوکنفرانس';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('اطلاعات پایه')->schema([
                Forms\Components\Select::make('organization_id')
                    ->label('سازمان')
                    ->relationship('organization', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),

                Forms\Components\TextInput::make('name')
                    ->label('نام')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Select::make('driver')
                    ->label('Driver')
                    ->options(VideoConferenceDriver::options())
                    ->required()
                    ->reactive(),

                Forms\Components\Toggle::make('is_active')
                    ->label('فعال')
                    ->default(true),

                Forms\Components\Toggle::make('is_default')
                    ->label('پیش‌فرض سازمان')
                    ->helperText('در صورت true، انتخاب پیش‌فرض هنگام ایجاد اتاق است.'),
            ])->columns(2),

            Forms\Components\Section::make('پیکربندی Driver')
                ->description('تنظیمات هر driver متفاوت است. این مقادیر به‌صورت encrypted ذخیره می‌شوند.')
                ->schema(function (Forms\Get $get) {
                    return self::driverConfigFields($get('driver'));
                })
                ->columns(2)
                ->visible(fn (Forms\Get $get) => filled($get('driver'))),

            Forms\Components\Section::make('قابلیت‌ها و محدودیت‌ها')->schema([
                Forms\Components\TextInput::make('max_concurrent_meetings')
                    ->label('حداکثر جلسات همزمان')
                    ->numeric()
                    ->helperText('خالی = نامحدود'),

                Forms\Components\TextInput::make('max_participants_per_meeting')
                    ->label('حداکثر شرکت‌کنندگان هر جلسه')
                    ->numeric(),

                Forms\Components\Toggle::make('supports_recording')->label('پشتیبانی از ضبط'),
                Forms\Components\Toggle::make('supports_streaming')->label('پشتیبانی از پخش زنده'),
                Forms\Components\Toggle::make('supports_breakout_rooms')->label('Breakout Rooms'),
            ])->columns(2)->collapsible(),
        ]);
    }

    private static function driverConfigFields(?string $driver): array
    {
        return match ($driver) {
            'alocom' => [
                Forms\Components\TextInput::make('config.api_base_url')
                    ->label('API Base URL')
                    ->required()
                    ->url(),
                Forms\Components\TextInput::make('config.api_token')
                    ->label('API Token')
                    ->required()
                    ->password()
                    ->revealable(),
                Forms\Components\TextInput::make('config.tenant_id')
                    ->label('Tenant ID')
                    ->required(),
            ],
            'jitsi' => [
                Forms\Components\TextInput::make('config.base_url')
                    ->label('Base URL')
                    ->required()
                    ->url(),
                Forms\Components\TextInput::make('config.jwt_secret')
                    ->label('JWT Secret')
                    ->required()
                    ->password()
                    ->revealable(),
                Forms\Components\TextInput::make('config.jwt_app_id')
                    ->label('JWT App ID')
                    ->required(),
                Forms\Components\TextInput::make('config.management_url')
                    ->label('Management URL (اختیاری)')
                    ->helperText('برای استفاده از Jibri recording'),
                Forms\Components\TextInput::make('config.management_token')
                    ->label('Management Token (اختیاری)')
                    ->password()
                    ->revealable(),
            ],
            'bigbluebutton' => [
                Forms\Components\TextInput::make('config.base_url')
                    ->label('Base URL')
                    ->required()
                    ->url(),
                Forms\Components\TextInput::make('config.shared_secret')
                    ->label('Shared Secret')
                    ->required()
                    ->password()
                    ->revealable(),
                Forms\Components\TextInput::make('config.logout_url')
                    ->label('Logout URL')
                    ->url(),
            ],
            'null' => [
                Forms\Components\TextInput::make('config.manual_host_url')
                    ->label('Host URL پیش‌فرض')
                    ->url()
                    ->helperText('اگر تنظیم نشود، یک URL مصنوعی تولید می‌شود'),
                Forms\Components\TextInput::make('config.manual_attendee_url')
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
                    ->badge()
                    ->formatStateUsing(fn (VideoConferenceDriver $d) => $d->label()),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('فعال')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_default')
                    ->label('پیش‌فرض')
                    ->boolean(),

                Tables\Columns\TextColumn::make('health_status')
                    ->label('سلامت')
                    ->badge()
                    ->color(fn (HealthStatus $s) => $s->color())
                    ->formatStateUsing(fn (HealthStatus $s) => $s->label()),

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
                Tables\Filters\TernaryFilter::make('is_active')->label('فعال'),
                Tables\Filters\SelectFilter::make('driver')
                    ->label('Driver')
                    ->options(VideoConferenceDriver::options()),
                Tables\Filters\SelectFilter::make('health_status')
                    ->label('سلامت')
                    ->options(collect(HealthStatus::cases())
                        ->mapWithKeys(fn ($s) => [$s->value => $s->label()])
                        ->toArray()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('check_health')
                    ->label('بررسی سلامت')
                    ->icon('heroicon-o-heart')
                    ->color('info')
                    ->action(function (VideoConferenceProvider $provider) {
                        $result = app(CheckProviderHealthAction::class)->execute($provider);
                        Notification::make()
                            ->title("سلامت: {$result->health_status->label()}")
                            ->body($result->health_message ?? '')
                            ->{$result->health_status->isUsable() ? 'success' : 'danger'}()
                            ->send();
                    }),

                Tables\Actions\Action::make('set_default')
                    ->label('پیش‌فرض کن')
                    ->icon('heroicon-o-star')
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

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\VideoConferenceProviderResource\RelationManagers\RoomsRelationManager::class,
        ];
    }
}
