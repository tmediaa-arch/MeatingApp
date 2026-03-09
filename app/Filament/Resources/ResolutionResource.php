<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domains\Resolutions\Enums\ResolutionStatus;
use App\Domains\Resolutions\Enums\ResolutionType;
use App\Domains\Resolutions\Models\Resolution;
use App\Filament\Resources\ResolutionResource\Pages;
use App\Filament\Resources\ResolutionResource\RelationManagers;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ResolutionResource extends Resource
{
    protected static ?string $model = Resolution::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationGroup = 'مدیریت پس از جلسه';
    protected static ?string $navigationLabel = 'مصوبات';
    protected static ?string $modelLabel = 'مصوبه';
    protected static ?string $pluralModelLabel = 'مصوبات';
    protected static ?int $navigationSort = 20;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('اطلاعات کلی')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('resolution_number')
                        ->label('شماره مصوبه')
                        ->disabled()
                        ->dehydrated(false),
                    Forms\Components\Select::make('minute_id')
                        ->label('صورتجلسه')
                        ->relationship('minute', 'minute_number')
                        ->required()
                        ->searchable(),
                    Forms\Components\TextInput::make('title')
                        ->label('عنوان')
                        ->required()
                        ->columnSpanFull(),
                    Forms\Components\RichEditor::make('content')
                        ->label('متن مصوبه')
                        ->required()
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('rationale')
                        ->label('دلیل/توجیه')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('دسته‌بندی و اولویت')
                ->columns(3)
                ->schema([
                    Forms\Components\Select::make('type')
                        ->label('نوع')
                        ->options(ResolutionType::options())
                        ->required(),
                    Forms\Components\Select::make('priority')
                        ->label('اولویت')
                        ->options([
                            'critical' => 'بحرانی',
                            'high' => 'بالا',
                            'normal' => 'عادی',
                            'low' => 'پایین',
                        ])
                        ->default('normal'),
                    Forms\Components\DatePicker::make('due_date')
                        ->label('مهلت'),
                ]),

            Forms\Components\Section::make('رأی‌گیری')
                ->columns(2)
                ->schema([
                    Forms\Components\Toggle::make('requires_voting')
                        ->label('نیاز به رأی‌گیری دارد؟')
                        ->live(),
                    Forms\Components\Select::make('voting_type')
                        ->label('نوع رأی‌گیری')
                        ->options([
                            'open' => 'باز',
                            'secret' => 'مخفی',
                            'weighted' => 'وزنی',
                        ])
                        ->visible(fn ($get) => $get('requires_voting')),
                    Forms\Components\TextInput::make('quorum_required')
                        ->label('حد نصاب')
                        ->numeric()
                        ->visible(fn ($get) => $get('requires_voting')),
                    Forms\Components\TextInput::make('majority_threshold_percent')
                        ->label('آستانه اکثریت (٪)')
                        ->numeric()
                        ->default(50)
                        ->visible(fn ($get) => $get('requires_voting')),
                ]),
        ]);
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
                    ->badge()
                    ->formatStateUsing(fn (ResolutionType $s) => $s->label()),
                Tables\Columns\TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge()
                    ->color(fn (ResolutionStatus $s) => $s->color())
                    ->formatStateUsing(fn (ResolutionStatus $s) => $s->label()),
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
                Tables\Filters\SelectFilter::make('status')
                    ->options(ResolutionStatus::options()),
                Tables\Filters\SelectFilter::make('type')
                    ->options(ResolutionType::options()),
                Tables\Filters\Filter::make('overdue')
                    ->label('مهلت‌گذشته')
                    ->query(fn ($q) => $q->overdue()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
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
