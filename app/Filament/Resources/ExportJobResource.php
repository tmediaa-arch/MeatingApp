<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domains\Exports\Enums\ExportStatus;
use App\Domains\Exports\Enums\ExportType;
use App\Domains\Exports\Models\ExportJob;
use App\Filament\Resources\ExportJobResource\Pages;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExportJobResource extends Resource
{
    protected static ?string $model = ExportJob::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;
    protected static string|\UnitEnum|null $navigationGroup = 'گزارش‌ها';
    protected static ?int $navigationSort = 3;
    protected static ?string $recordTitleAttribute = 'label';

    public static function getModelLabel(): string
    {
        return 'Export';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Exportها';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('شناسه')->sortable(),
                Tables\Columns\TextColumn::make('label')->label('عنوان')->limit(60)->wrap(),
                Tables\Columns\TextColumn::make('export_type')
                    ->label('نوع')->badge(),
                Tables\Columns\TextColumn::make('format')
                    ->label('فرمت')->badge()->formatStateUsing(fn ($state) => strtoupper((string) $state)),
                Tables\Columns\TextColumn::make('status')
                    ->label('وضعیت')->badge(),
                Tables\Columns\TextColumn::make('row_count')->label('رکورد')->sortable(),
                Tables\Columns\TextColumn::make('duration_ms')
                    ->label('زمان')->formatStateUsing(fn ($state) => $state ? "{$state} ms" : '—'),
                Tables\Columns\TextColumn::make('requestedBy.name')->label('درخواست‌کننده'),
                Tables\Columns\TextColumn::make('created_at')->label('زمان')->dateTime('Y-m-d H:i')->sortable(),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label('انقضا')->dateTime('Y-m-d H:i')->color('warning'),
            ])
            ->filters([
                SelectFilter::make('export_type')
                    ->options(ExportType::class),
                SelectFilter::make('status')
                    ->options(ExportStatus::class),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    Action::make('download')
                        ->label('دانلود')
                        ->icon(Heroicon::OutlinedArrowDownTray)
                        ->color('success')
                        ->visible(fn (ExportJob $record) => $record->output_file_id !== null
                            && (! $record->expires_at || $record->expires_at->isFuture()))
                        ->url(fn (ExportJob $record) => route('files.download', $record->output_file_id), shouldOpenInNewTab: true),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExportJobs::route('/'),
            'view' => Pages\ViewExportJob::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['requestedBy', 'outputFile']);
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
