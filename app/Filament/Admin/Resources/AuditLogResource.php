<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domains\Audit\Models\AuditLog;
use App\Filament\Admin\Resources\AuditLogResource\Pages;
use App\Filament\Admin\Schemas\FormLayout;
use Filament\Actions\ViewAction;
use App\Filament\Forms\Components\JalaliDatePicker;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * AuditLogResource — صرفاً مشاهده‌ای
 * هیچ‌گونه ایجاد، ویرایش یا حذف مجاز نیست.
 */
class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;
    protected static string|\UnitEnum|null $navigationGroup = 'ممیزی و امنیت';
    protected static ?int $navigationSort = 80;
    protected static ?string $recordTitleAttribute = 'event';

    public static function getModelLabel(): string
    {
        return 'لاگ ممیزی';
    }

    public static function getPluralModelLabel(): string
    {
        return 'لاگ‌های ممیزی';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components(FormLayout::withSidebar(
            main: [
                Section::make('رویداد')
                    ->description('جزئیات کلی رویداد ثبت‌شده')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('event')->label('رویداد')->badge(),
                        TextEntry::make('action_category')->label('دسته')->badge()->placeholder('—'),
                        TextEntry::make('severity')->label('شدت')->badge()->placeholder('—'),
                        TextEntry::make('performed_at')->label('زمان')->dateTime('Y/m/d H:i:s'),
                        TextEntry::make('description')->label('شرح')->columnSpanFull()->placeholder('—'),
                    ]),

                Section::make('تغییرات')
                    ->description('مقادیر قبل و بعد از تغییر')
                    ->collapsible()
                    ->schema([
                        KeyValueEntry::make('old_values')->label('مقادیر قبلی')->columnSpanFull(),
                        KeyValueEntry::make('new_values')->label('مقادیر جدید')->columnSpanFull(),
                        KeyValueEntry::make('context')->label('زمینه')->columnSpanFull(),
                    ]),
            ],
            sidebar: [
                Section::make('کاربر')
                    ->schema([
                        TextEntry::make('user_display_name')->label('کاربر')->placeholder('—'),
                        TextEntry::make('user_id')->label('شناسه کاربر')->placeholder('—'),
                        TextEntry::make('ip_address')->label('IP')->placeholder('—'),
                        TextEntry::make('request_method')->label('متد درخواست')->placeholder('—'),
                        TextEntry::make('request_url')->label('آدرس درخواست')->placeholder('—'),
                        TextEntry::make('user_agent')->label('User Agent')->placeholder('—'),
                    ]),

                Section::make('موجودیت مرتبط')
                    ->schema([
                        TextEntry::make('auditable_type')->label('نوع')->placeholder('—'),
                        TextEntry::make('auditable_id')->label('شناسه')->placeholder('—'),
                        TextEntry::make('tag')->label('برچسب')->placeholder('—'),
                        TextEntry::make('correlation_id')->label('Correlation ID')->placeholder('—'),
                    ]),
            ],
        ));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('performed_at')
                    ->label('زمان')
                    ->dateTime('Y/m/d H:i:s')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('user_display_name')
                    ->label('کاربر')
                    ->searchable()
                    ->description(fn (AuditLog $r) => $r->user?->username),

                Tables\Columns\TextColumn::make('event')
                    ->label('رویداد')
                    ->badge()
                    ->searchable(),

                Tables\Columns\TextColumn::make('action_category')
                    ->label('دسته')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('توضیح')
                    ->limit(80)
                    ->tooltip(fn (AuditLog $r) => $r->description),

                Tables\Columns\TextColumn::make('severity')
                    ->label('شدت')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'critical' => 'danger',
                        'warning' => 'warning',
                        'notice' => 'info',
                        'info' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),

                Tables\Columns\TextColumn::make('auditable_type')
                    ->label('موجودیت')
                    ->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : '—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('correlation_id')
                    ->label('Correlation')
                    ->limit(8)
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('event')
                    ->label('رویداد')
                    ->options(fn () => AuditLog::query()
                        ->distinct()
                        ->pluck('event', 'event')
                        ->toArray()),

                SelectFilter::make('severity')
                    ->label('شدت')
                    ->options([
                        'debug' => 'Debug',
                        'info' => 'Info',
                        'notice' => 'Notice',
                        'warning' => 'Warning',
                        'critical' => 'Critical',
                    ]),

                SelectFilter::make('user_id')
                    ->label('کاربر')
                    ->relationship('user', 'username')
                    ->searchable()
                    ->preload(),

                Filter::make('performed_at')
                    ->schema([
                        JalaliDatePicker::make('from')->label('از'),
                        JalaliDatePicker::make('until')->label('تا'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('performed_at', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('performed_at', '<=', $date));
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('performed_at', 'desc')
            ->paginated([25, 50, 100]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuditLogs::route('/'),
            'view' => Pages\ViewAuditLog::route('/{record}'),
        ];
    }
}
