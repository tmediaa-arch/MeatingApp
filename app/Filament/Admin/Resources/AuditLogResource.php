<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domains\Audit\Models\AuditLog;
use App\Filament\Admin\Resources\AuditLogResource\Pages;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
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
                        DatePicker::make('from')->label('از'),
                        DatePicker::make('until')->label('تا'),
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
