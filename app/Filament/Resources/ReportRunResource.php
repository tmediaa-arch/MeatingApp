<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domains\Reports\Enums\ReportRunStatus;
use App\Domains\Reports\Models\ReportRun;
use App\Filament\Resources\ReportRunResource\Pages;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReportRunResource extends Resource
{
    protected static ?string $model = ReportRun::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;
    protected static string|\UnitEnum|null $navigationGroup = 'گزارش‌ها';
    protected static ?int $navigationSort = 2;
    protected static ?string $recordTitleAttribute = 'id';

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
                    ->label('وضعیت')->badge(),
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
                SelectFilter::make('report_id')
                    ->label('گزارش')
                    ->relationship('report', 'display_name'),
                SelectFilter::make('status')
                    ->options(ReportRunStatus::class),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    Action::make('download')
                        ->label('دانلود')
                        ->icon(Heroicon::OutlinedArrowDownTray)
                        ->color('success')
                        ->visible(fn (ReportRun $record) => $record->output_file_id !== null)
                        ->url(fn (ReportRun $record) => route('files.download', $record->output_file_id), shouldOpenInNewTab: true),
                ]),
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
        return false;
    }
}
