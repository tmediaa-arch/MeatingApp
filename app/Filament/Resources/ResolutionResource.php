<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domains\Resolutions\Enums\ResolutionStatus;
use App\Domains\Resolutions\Enums\ResolutionType;
use App\Domains\Resolutions\Models\Resolution;
use App\Filament\Admin\Schemas\FormLayout;
use App\Filament\Resources\ResolutionResource\Pages;
use App\Filament\Resources\ResolutionResource\RelationManagers;
use App\Filament\Forms\Components\JalaliDatePicker;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ResolutionResource extends Resource
{
    protected static ?string $model = Resolution::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;
    protected static string|\UnitEnum|null $navigationGroup = 'مدیریت پس از جلسه';
    protected static ?string $navigationLabel = 'مصوبات';
    protected static ?string $modelLabel = 'مصوبه';
    protected static ?string $pluralModelLabel = 'مصوبات';
    protected static ?string $recordTitleAttribute = 'title';
    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->components(FormLayout::withSidebar(
            main: [
                Section::make('اطلاعات کلی')
                    ->columns(2)
                    ->schema([
                        TextInput::make('resolution_number')
                            ->label('شماره مصوبه')
                            ->disabled()
                            ->dehydrated(false),
                        Select::make('minute_id')
                            ->label('صورتجلسه')
                            ->relationship('minute', 'minute_number')
                            ->required()
                            ->searchable(),
                        TextInput::make('title')
                            ->label('عنوان')
                            ->required()
                            ->columnSpanFull(),
                        RichEditor::make('content')
                            ->label('متن مصوبه')
                            ->required()
                            ->columnSpanFull(),
                        Textarea::make('rationale')
                            ->label('دلیل/توجیه')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Section::make('رأی‌گیری')
                    ->columns(2)
                    ->schema([
                        Toggle::make('requires_voting')
                            ->label('نیاز به رأی‌گیری دارد؟')
                            ->live(),
                        Select::make('voting_type')
                            ->label('نوع رأی‌گیری')
                            ->options([
                                'open' => 'باز',
                                'secret' => 'مخفی',
                                'weighted' => 'وزنی',
                            ])
                            ->visible(fn ($get) => $get('requires_voting')),
                        TextInput::make('quorum_required')
                            ->label('حد نصاب')
                            ->numeric()
                            ->visible(fn ($get) => $get('requires_voting')),
                        TextInput::make('majority_threshold_percent')
                            ->label('آستانه اکثریت (٪)')
                            ->numeric()
                            ->default(50)
                            ->visible(fn ($get) => $get('requires_voting')),
                    ]),
            ],
            sidebar: [
                Section::make('دسته‌بندی و اولویت')
                    ->schema([
                        Select::make('type')
                            ->label('نوع')
                            ->options(ResolutionType::class)
                            ->required(),
                        Select::make('priority')
                            ->label('اولویت')
                            ->options([
                                'critical' => 'بحرانی',
                                'high' => 'بالا',
                                'normal' => 'عادی',
                                'low' => 'پایین',
                            ])
                            ->default('normal'),
                        JalaliDatePicker::make('due_date')
                            ->label('مهلت'),
                    ]),
            ],
        ));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('resolution_number')
                    ->label('شماره')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('title')
                    ->label('عنوان')
                    ->limit(40)
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('نوع')
                    ->badge(),
                Tables\Columns\TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge(),
                Tables\Columns\TextColumn::make('voting_progress')
                    ->label('رأی‌گیری')
                    ->state(fn ($record) => $record->requires_voting
                        ? "{$record->voters_total} رأی ({$record->voting_progress['percent_for']}% موافق)"
                        : '—'),
                Tables\Columns\TextColumn::make('due_date')
                    ->label('مهلت')
                    ->date('Y/m/d')
                    ->color(fn ($state, $record) => $record->isOverdue() ? 'danger' : null),
                Tables\Columns\TextColumn::make('tasks_count')
                    ->label('وظایف')
                    ->counts('tasks'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(ResolutionStatus::class),
                SelectFilter::make('type')
                    ->options(ResolutionType::class),
                Filter::make('overdue')
                    ->label('مهلت‌گذشته')
                    ->query(fn ($q) => $q->overdue()),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                ]),
            ])
            ->groupedBulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\VotesRelationManager::class,
            RelationManagers\AssigneesRelationManager::class,
            RelationManagers\TasksRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListResolutions::route('/'),
            'create' => Pages\CreateResolution::route('/create'),
            'view' => Pages\ViewResolution::route('/{record}'),
            'edit' => Pages\EditResolution::route('/{record}/edit'),
        ];
    }
}
