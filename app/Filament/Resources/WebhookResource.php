<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domains\Integrations\Enums\IntegrationHealthStatus;
use App\Domains\Integrations\Models\ApiWebhook;
use App\Filament\Admin\Schemas\FormLayout;
use App\Filament\Resources\WebhookResource\Pages;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class WebhookResource extends Resource
{
    protected static ?string $model = ApiWebhook::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;
    protected static ?string $navigationGroup = 'یکپارچه‌سازی';
    protected static ?int $navigationSort = 2;
    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return 'Webhook';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Webhookها';
    }

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

    public static function form(Schema $schema): Schema
    {
        return $schema->components(FormLayout::withSidebar(
            main: [
                Section::make('اطلاعات Webhook')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('نام')->required()->maxLength(200),
                        TextInput::make('url')
                            ->label('URL مقصد')->url()->required()->maxLength(500),
                    ]),

                Section::make('Events')->schema([
                    CheckboxList::make('events')
                        ->label('رویدادهای تحت گوش')
                        ->options(self::availableEvents())
                        ->columns(2)
                        ->required(),
                ]),

                Section::make('پیکربندی Delivery')
                    ->columns(3)
                    ->schema([
                        TextInput::make('max_retries')->label('حداکثر تلاش')->numeric()->default(5),
                        TextInput::make('timeout_seconds')->label('Timeout (ثانیه)')->numeric()->default(30),
                        TextInput::make('secret')
                            ->label('Secret')
                            ->helperText('برای امضای HMAC — در صورت خالی بودن، خودکار تولید می‌شود')
                            ->password()
                            ->revealable()
                            ->default(fn () => ApiWebhook::generateSecret())
                            ->required(),
                    ]),

                Section::make('Headers اضافی')
                    ->collapsible()
                    ->schema([
                        KeyValue::make('headers')
                            ->label('Headers')
                            ->columnSpanFull(),
                    ]),
            ],
            sidebar: [
                Section::make('وضعیت')
                    ->schema([
                        Toggle::make('is_active')->label('فعال')->default(true),
                        Toggle::make('verify_ssl')->label('بررسی SSL')->default(true),
                    ]),
            ],
        ));
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
                    ->label('سلامت')->badge(),
                Tables\Columns\TextColumn::make('consecutive_failures')
                    ->label('شکست‌های متوالی')->badge()
                    ->color(fn ($state) => $state > 5 ? 'danger' : ($state > 0 ? 'warning' : 'success')),
                Tables\Columns\TextColumn::make('last_success_at')
                    ->label('آخرین موفقیت')->dateTime('Y-m-d H:i'),
            ])
            ->filters([
                TernaryFilter::make('is_active'),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ]);
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
