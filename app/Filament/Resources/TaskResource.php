<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domains\Tasks\Enums\TaskPriority;
use App\Domains\Tasks\Enums\TaskStatus;
use App\Domains\Tasks\Enums\TaskType;
use App\Domains\Tasks\Models\Task;
use App\Filament\Resources\TaskResource\Pages;
use App\Filament\Resources\TaskResource\RelationManagers;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TaskResource extends Resource
{
    protected static ?string $model = Task::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'مدیریت پس از جلسه';
    protected static ?string $navigationLabel = 'وظایف';
    protected static ?string $modelLabel = 'وظیفه';
    protected static ?string $pluralModelLabel = 'وظایف';
    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('اطلاعات کلی')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('task_number')
                        ->label('شماره وظیفه')
                        ->disabled()
                        ->dehydrated(false),
                    Forms\Components\TextInput::make('title')
                        ->label('عنوان')
                        ->required()
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('description')
                        ->label('شرح')
                        ->rows(4)
                        ->columnSpanFull(),
                    Forms\Components\Select::make('type')
                        ->label('نوع')
                        ->options(TaskType::options())
                        ->required()
                        ->default(TaskType::Action->value),
                    Forms\Components\Select::make('priority')
                        ->label('اولویت')
                        ->options(TaskPriority::options())
                        ->required()
                        ->default(TaskPriority::Normal->value),
                ]),

            Forms\Components\Section::make('ارجاع')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('assignee_employee_id')
                        ->label('مجری')
                        ->relationship('assignee', 'first_name')
                        ->searchable()
                        ->preload(),
                    Forms\Components\Select::make('supervisor_employee_id')
                        ->label('ناظر')
                        ->relationship('supervisor', 'first_name')
                        ->searchable()
                        ->preload(),
                    Forms\Components\Select::make('approver_employee_id')
                        ->label('تأییدکننده')
                        ->relationship('approver', 'first_name')
                        ->searchable()
                        ->preload(),
                    Forms\Components\DatePicker::make('due_date')
                        ->label('مهلت'),
                ]),

            Forms\Components\Section::make('پیگیری')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('status')
                        ->label('وضعیت')
                        ->options(TaskStatus::options())
                        ->disabled()
                        ->dehydrated(false),
                    Forms\Components\TextInput::make('estimated_hours')
                        ->label('ساعت برآوردی')
                        ->numeric(),
                    Forms\Components\Placeholder::make('progress_display')
                        ->label('پیشرفت')
                        ->content(fn ($record) => $record ? "{$record->progress_percent}%" : '—'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('task_number')
                    ->label('شماره')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('title')
                    ->label('عنوان')
                    ->limit(40)
                    ->searchable(),
                Tables\Columns\TextColumn::make('priority')
                    ->label('اولویت')
                    ->badge()
                    ->color(fn (TaskPriority $p) => $p->color())
                    ->formatStateUsing(fn (TaskPriority $p) => $p->label()),
                Tables\Columns\TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge()
                    ->color(fn (TaskStatus $s) => $s->color())
                    ->formatStateUsing(fn (TaskStatus $s) => $s->label()),
                Tables\Columns\TextColumn::make('assignee.full_name')
                    ->label('مجری'),
                Tables\Columns\TextColumn::make('progress_percent')
                    ->label('پیشرفت')
                    ->state(fn ($r) => "{$r->progress_percent}%"),
                Tables\Columns\TextColumn::make('due_date')
                    ->label('مهلت')
                    ->date('Y/m/d')
                    ->color(fn ($state, $record) => $record->is_overdue ? 'danger' : null),
                Tables\Columns\IconColumn::make('is_overdue')
                    ->label('تأخیر')
                    ->boolean()
                    ->visible(fn () => true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(TaskStatus::options()),
                Tables\Filters\SelectFilter::make('priority')->options(TaskPriority::options()),
                Tables\Filters\Filter::make('overdue')
                    ->label('فقط تأخیردار')
                    ->query(fn ($q) => $q->where('is_overdue', true)),
                Tables\Filters\Filter::make('mine')
                    ->label('وظایف من')
                    ->query(fn ($q) => $q->forUser(auth()->user())),
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
            RelationManagers\UpdatesRelationManager::class,
            RelationManagers\ExtensionsRelationManager::class,
            RelationManagers\AttachmentsRelationManager::class,
            RelationManagers\SubtasksRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTasks::route('/'),
            'create' => Pages\CreateTask::route('/create'),
            'view' => Pages\ViewTask::route('/{record}'),
            'edit' => Pages\EditTask::route('/{record}/edit'),
        ];
    }
}
