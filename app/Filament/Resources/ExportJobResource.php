<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domains\Exports\Enums\ExportStatus;
use App\Domains\Exports\Enums\ExportType;
use App\Domains\Exports\Models\ExportJob;
use App\Filament\Resources\ExportJobResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExportJobResource extends Resource
{
    protected static ?string $model = ExportJob::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';
    protected static ?string $navigationGroup = 'گزارش‌ها';
    protected static ?int $navigationSort = 3;

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
                    ->label('نوع')->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof ExportType ? $state->label() : $state),
                Tables\Columns\TextColumn::make('format')
                    ->label('فرمت')->badge()->formatStateUsing(fn ($state) => strtoupper((string) $state)),
                Tables\Columns\TextColumn::make('status')
                    ->label('وضعیت')->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof ExportStatus ? $state->label() : $state)
                    ->color(fn ($state) => $state instanceof ExportStatus ? $state->color() : 'gray'),
                Tables\Columns\TextColumn::make('row_count')->label('رکورد')->sortable(),
                Tables\Columns\TextColumn::make('duration_ms')
                    ->label('زمان')->formatStateUsing(fn ($state) => $state ? "{$state} ms" : '—'),
                Tables\Columns\TextColumn::make('requestedBy.name')->label('درخواست‌کننده'),
                Tables\Columns\TextColumn::make('created_at')->label('زمان')->dateTime('Y-m-d H:i')->sortable(),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label('انقضا')->dateTime('Y-m-d H:i')->color('warning'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('export_type')
                    ->options(collect(ExportType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()])),
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(ExportStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
            ])
            ->actions([
                Tables\Actions\Action::make('download')
                    ->label('دانلود')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn (ExportJob $record) => $record->output_file_id !== null
                        && (! $record->expires_at || $record->expires_at->isFuture()))
                    ->url(fn (ExportJob $record) => route('files.download', $record->output_file_id), shouldOpenInNewTab: true),
                Tables\Actions\ViewAction::make(),
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
