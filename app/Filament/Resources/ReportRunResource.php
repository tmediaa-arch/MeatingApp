<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domains\Reports\Enums\ReportRunStatus;
use App\Domains\Reports\Models\ReportRun;
use App\Filament\Resources\ReportRunResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReportRunResource extends Resource
{
    protected static ?string $model = ReportRun::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationGroup = 'گزارش‌ها';
    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return 'اجرای گزارش';
    }

    public static function getPluralModelLabel(): string
    {
        return 'تاریخچه اجراها';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('شناسه')->sortable(),
                Tables\Columns\TextColumn::make('report.display_name')
                    ->label('گزارش')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('requestedBy.name')
                    ->label('اجراکننده')->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('وضعیت')->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof ReportRunStatus ? $state->label() : $state)
                    ->color(fn ($state) => $state instanceof ReportRunStatus ? $state->color() : 'gray'),
                Tables\Columns\TextColumn::make('row_count')->label('رکورد')->sortable(),
                Tables\Columns\TextColumn::make('output_format')->label('فرمت')->badge(),
                Tables\Columns\TextColumn::make('duration_ms')
                    ->label('زمان')->formatStateUsing(fn ($state) => $state ? "{$state} ms" : '—'),
                Tables\Columns\TextColumn::make('cached_until')
                    ->label('Cache تا')->dateTime('Y-m-d H:i')->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('زمان')->dateTime('Y-m-d H:i:s')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('report_id')
                    ->label('گزارش')
                    ->relationship('report', 'display_name'),
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(ReportRunStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
            ])
            ->actions([
                Tables\Actions\Action::make('download')
                    ->label('دانلود')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn (ReportRun $record) => $record->output_file_id !== null)
                    ->url(fn (ReportRun $record) => route('files.download', $record->output_file_id), shouldOpenInNewTab: true),
                Tables\Actions\ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReportRuns::route('/'),
            'view' => Pages\ViewReportRun::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['report', 'requestedBy', 'outputFile']);
    }

    public static function canCreate(): bool
    {
        return false; // اجراها فقط از طریق Action ساخته می‌شوند
    }
}
