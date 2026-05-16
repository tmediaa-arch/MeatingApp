<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domains\Reports\Enums\ReportCategory;
use App\Domains\Reports\Enums\ReportFormat;
use App\Domains\Reports\Models\Report;
use App\Filament\Admin\Schemas\FormLayout;
use App\Filament\Resources\ReportResource\Pages;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReportResource extends Resource
{
    protected static ?string $model = Report::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;
    protected static string|\UnitEnum|null $navigationGroup = 'گزارش‌ها';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'display_name';

    public static function getModelLabel(): string
    {
        return 'گزارش';
    }

    public static function getPluralModelLabel(): string
    {
        return 'گزارش‌ها';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(FormLayout::withSidebar(
            main: [
                Section::make('اطلاعات گزارش')
                    ->columns(2)
                    ->schema([
                        TextInput::make('display_name')
                            ->label('نام نمایشی')->required()->maxLength(200),

                        TextInput::make('key')
                            ->label('کلید')->required()->maxLength(100)
                            ->helperText('یکتا، snake_case (مثلاً: meetings.summary)'),

                        Select::make('category')
                            ->label('دسته')
                            ->options(ReportCategory::class)
                            ->required(),

                        Textarea::make('description')
                            ->label('توضیحات')->rows(3),

                        TextInput::make('handler_class')
                            ->label('کلاس Handler')->required()->maxLength(200)
                            ->disabled(fn ($record) => $record?->is_system),
                    ]),

                Section::make('فرمت‌ها')
                    ->schema([
                        CheckboxList::make('supported_formats')
                            ->label('فرمت‌های پشتیبانی‌شده')
                            ->options(ReportFormat::class),
                    ]),
            ],
            sidebar: [
                Section::make('Cache و وضعیت')
                    ->schema([
                        Toggle::make('is_cacheable')->label('قابل Cache'),
                        TextInput::make('cache_ttl_minutes')
                            ->label('TTL Cache (دقیقه)')->numeric()->default(60),
                        Toggle::make('is_active')->label('فعال')->default(true),
                    ]),
            ],
        ));
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
                    ->label('دسته')->badge(),
                Tables\Columns\IconColumn::make('is_cacheable')->label('Cache')->boolean(),
                Tables\Columns\IconColumn::make('is_system')->label('سیستمی')->boolean(),
                Tables\Columns\IconColumn::make('is_active')->label('فعال')->boolean(),
                Tables\Columns\TextColumn::make('runs_count')
                    ->label('تعداد اجراها')->counts('runs')->sortable(),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->options(ReportCategory::class),
                TernaryFilter::make('is_active')->label('فعال'),
                TernaryFilter::make('is_system')->label('سیستمی'),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('run')
                        ->label('اجرا')
                        ->icon(Heroicon::OutlinedPlay)
                        ->color('primary')
                        ->url(fn (Report $record) => Pages\RunReport::getUrl(['record' => $record->getKey()]))
                        ->visible(fn (Report $record) => $record->is_active),
                    Action::make('history')
                        ->label('تاریخچه')
                        ->icon(Heroicon::OutlinedClock)
                        ->color('gray')
                        ->url(fn (Report $record) => ReportRunResource::getUrl('index', ['tableFilters[report_id][value]' => $record->id])),
                    EditAction::make()
                        ->visible(fn (Report $record) => !$record->is_system),
                ]),
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
