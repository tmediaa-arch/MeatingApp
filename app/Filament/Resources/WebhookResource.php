<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domains\Integrations\Enums\IntegrationHealthStatus;
use App\Domains\Integrations\Models\ApiWebhook;
use App\Filament\Resources\WebhookResource\Pages;
use App\Filament\Resources\WebhookResource\RelationManagers;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WebhookResource extends Resource
{
    protected static ?string $model = ApiWebhook::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';
    protected static ?string $navigationGroup = 'یکپارچه‌سازی';
    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return 'Webhook';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Webhookها';
    }

    /**
     * Events قابل ارسال — لیست استاتیک از همه domain events
     */
    private static function availableEvents(): array
    {
        return [
            'meeting.created' => 'جلسه ایجاد شد',
            'meeting.updated' => 'جلسه ویرایش شد',
            'meeting.cancelled' => 'جلسه لغو شد',
            'meeting.completed' => 'جلسه برگزار شد',
            'minute.published' => 'صورتجلسه منتشر شد',
            'minute.signed' => 'صورتجلسه امضا شد',
            'resolution.approved' => 'مصوبه تأیید شد',
            'resolution.completed' => 'مصوبه اجرا شد',
            'task.assigned' => 'وظیفه واگذار شد',
            'task.completed' => 'وظیفه تکمیل شد',
            'task.overdue' => 'وظیفه معوقه شد',
            'workflow.started' => 'گردش کار شروع شد',
            'workflow.completed' => 'گردش کار پایان یافت',
            'video_conference.started' => 'ویدئوکنفرانس شروع شد',
        ];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('اطلاعات Webhook')->schema([
                Forms\Components\TextInput::make('name')
                    ->label('نام')->required()->maxLength(200),
                Forms\Components\TextInput::make('url')
                    ->label('URL مقصد')->url()->required()->maxLength(500),
                Forms\Components\Toggle::make('is_active')->label('فعال')->default(true),
                Forms\Components\Toggle::make('verify_ssl')->label('بررسی SSL')->default(true),
            ])->columns(2),

            Forms\Components\Section::make('Events')->schema([
                Forms\Components\CheckboxList::make('events')
                    ->label('رویدادهای تحت گوش')
                    ->options(self::availableEvents())
                    ->columns(2)
                    ->required(),
            ]),

            Forms\Components\Section::make('پیکربندی Delivery')->schema([
                Forms\Components\TextInput::make('max_retries')->label('حداکثر تلاش')->numeric()->default(5),
                Forms\Components\TextInput::make('timeout_seconds')->label('Timeout (ثانیه)')->numeric()->default(30),
                Forms\Components\TextInput::make('secret')
                    ->label('Secret')
                    ->helperText('برای امضای HMAC — در صورت خالی بودن، خودکار تولید می‌شود')
                    ->password()
                    ->revealable()
                    ->default(fn () => ApiWebhook::generateSecret())
                    ->required(),
            ])->columns(3),

            Forms\Components\Section::make('Headers اضافی')->schema([
                Forms\Components\KeyValue::make('headers')
                    ->label('Headers')
                    ->columnSpanFull(),
            ])->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('نام')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('url')->label('URL')->limit(50)->copyable(),
                Tables\Columns\TextColumn::make('events')
                    ->label('Events')
                    ->formatStateUsing(fn ($state) => is_array($state) ? count($state) . ' رویداد' : '0')
                    ->badge(),
                Tables\Columns\IconColumn::make('is_active')->label('فعال')->boolean(),
                Tables\Columns\TextColumn::make('health_status')
                    ->label('سلامت')->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof IntegrationHealthStatus ? $state->label() : $state)
                    ->color(fn ($state) => $state instanceof IntegrationHealthStatus ? $state->color() : 'gray'),
                Tables\Columns\TextColumn::make('consecutive_failures')
                    ->label('شکست‌های متوالی')->badge()
                    ->color(fn ($state) => $state > 5 ? 'danger' : ($state > 0 ? 'warning' : 'success')),
                Tables\Columns\TextColumn::make('last_success_at')
                    ->label('آخرین موفقیت')->dateTime('Y-m-d H:i'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\DeliveriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWebhooks::route('/'),
            'create' => Pages\CreateWebhook::route('/create'),
            'edit' => Pages\EditWebhook::route('/{record}/edit'),
        ];
    }
}
