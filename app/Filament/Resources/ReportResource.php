<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domains\Reports\Enums\ReportCategory;
use App\Domains\Reports\Enums\ReportFormat;
use App\Domains\Reports\Models\Report;
use App\Filament\Resources\ReportResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReportResource extends Resource
{
    protected static ?string $model = Report::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static ?string $navigationGroup = 'گزارش‌ها';
    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return 'گزارش';
    }

    public static function getPluralModelLabel(): string
    {
        return 'گزارش‌ها';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('اطلاعات گزارش')
                ->schema([
                    Forms\Components\TextInput::make('display_name')
                        ->label('نام نمایشی')->required()->maxLength(200),

                    Forms\Components\TextInput::make('key')
                        ->label('کلید')->required()->maxLength(100)
                        ->helperText('یکتا، snake_case (مثلاً: meetings.summary)'),

                    Forms\Components\Select::make('category')
                        ->label('دسته')
                        ->options(collect(ReportCategory::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()]))
                        ->required(),

                    Forms\Components\Textarea::make('description')
                        ->label('توضیحات')->rows(3),

                    Forms\Components\TextInput::make('handler_class')
                        ->label('کلاس Handler')->required()->maxLength(200)
                        ->disabled(fn ($record) => $record?->is_system),
                ])->columns(2),

            Forms\Components\Section::make('فرمت‌ها و Cache')
                ->schema([
                    Forms\Components\CheckboxList::make('supported_formats')
                        ->label('فرمت‌های پشتیبانی‌شده')
                        ->options(collect(ReportFormat::cases())->mapWithKeys(fn ($f) => [$f->value => $f->label()])),

                    Forms\Components\Toggle::make('is_cacheable')->label('قابل Cache'),
                    Forms\Components\TextInput::make('cache_ttl_minutes')
                        ->label('TTL Cache (دقیقه)')->numeric()->default(60),
                    Forms\Components\Toggle::make('is_active')->label('فعال')->default(true),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('display_name')
                    ->label('نام')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('key')
                    ->label('کلید')->copyable()->fontFamily('mono')->size('sm'),
                Tables\Columns\TextColumn::make('category')
                    ->label('دسته')->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof ReportCategory ? $state->label() : $state),
                Tables\Columns\IconColumn::make('is_cacheable')->label('Cache')->boolean(),
                Tables\Columns\IconColumn::make('is_system')->label('سیستمی')->boolean(),
                Tables\Columns\IconColumn::make('is_active')->label('فعال')->boolean(),
                Tables\Columns\TextColumn::make('runs_count')
                    ->label('تعداد اجراها')->counts('runs')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->options(collect(ReportCategory::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])),
                Tables\Filters\TernaryFilter::make('is_active')->label('فعال'),
                Tables\Filters\TernaryFilter::make('is_system')->label('سیستمی'),
            ])
            ->actions([
                Tables\Actions\Action::make('run')
                    ->label('اجرا')
                    ->icon('heroicon-o-play')
                    ->color('primary')
                    ->url(fn (Report $record) => Pages\RunReport::getUrl(['record' => $record]))
                    ->visible(fn (Report $record) => $record->is_active),
                Tables\Actions\Action::make('history')
                    ->label('تاریخچه')
                    ->icon('heroicon-o-clock')
                    ->color('gray')
                    ->url(fn (Report $record) => ReportRunResource::getUrl('index', ['tableFilters[report_id][value]' => $record->id])),
                Tables\Actions\EditAction::make()
                    ->visible(fn (Report $record) => !$record->is_system),
            ])
            ->defaultSort('category');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReports::route('/'),
            'edit' => Pages\EditReport::route('/{record}/edit'),
            'run' => Pages\RunReport::route('/{record}/run'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }
}
