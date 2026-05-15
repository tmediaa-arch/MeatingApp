<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domains\Tasks\Enums\TaskPriority;
use App\Domains\Tasks\Enums\TaskStatus;
use App\Domains\Tasks\Enums\TaskType;
use App\Domains\Tasks\Models\Task;
use App\Filament\Admin\Schemas\FormLayout;
use App\Filament\Resources\TaskResource\Pages;
use App\Filament\Resources\TaskResource\RelationManagers;
use Ariaieboy\Jalali\Forms\Components\JalaliDatePicker;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TaskResource extends Resource
{
    protected static ?string $model = Task::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;
    protected static string|\UnitEnum|null $navigationGroup = 'مدیریت پس از جلسه';
    protected static ?string $navigationLabel = 'وظایف';
    protected static ?string $modelLabel = 'وظیفه';
    protected static ?string $pluralModelLabel = 'وظایف';
    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->components(FormLayout::withSidebar(
            main: [
                Section::make('اطلاعات کلی')
                    ->columns(2)
                    ->schema([
                        TextInput::make('task_number')
                            ->label('شماره وظیفه')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('title')
                            ->label('عنوان')
                            ->required()
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->label('شرح')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),

                Section::make('ارجاع')
                    ->columns(2)
                    ->schema([
                        Select::make('assignee_employee_id')
                            ->label('مجری')
                            ->relationship('assignee', 'first_name')
                            ->searchable()
                            ->preload(),
                        Select::make('supervisor_employee_id')
                            ->label('ناظر')
                            ->relationship('supervisor', 'first_name')
                            ->searchable()
                            ->preload(),
                        Select::make('approver_employee_id')
                            ->label('تأییدکننده')
                            ->relationship('approver', 'first_name')
                            ->searchable()
                            ->preload(),
                        JalaliDatePicker::make('due_date')
                            ->label('مهلت'),
                    ]),
            ],
            sidebar: [
                Section::make('دسته‌بندی')
                    ->schema([
                        Select::make('type')
                            ->label('نوع')
                            ->options(TaskType::class)
                            ->required()
                            ->default(TaskType::Action),
                        Select::make('priority')
                            ->label('اولویت')
                            ->options(TaskPriority::class)
                            ->required()
                            ->default(TaskPriority::Normal),
                    ]),

                Section::make('پیگیری')
                    ->schema([
                        Select::make('status')
                            ->label('وضعیت')
                            ->options(TaskStatus::class)
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('estimated_hours')
                            ->label('ساعت برآوردی')
                            ->numeric(),
                        TextEntry::make('progress_display')
                            ->label('پیشرفت')
                            ->state(fn ($record) => $record ? "{$record->progress_percent}%" : '—'),
                    ]),
            ],
        ));
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
                    ->badge(),
                Tables\Columns\TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge(),
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
                SelectFilter::make('status')->options(TaskStatus::class),
                SelectFilter::make('priority')->options(TaskPriority::class),
                Filter::make('overdue')
                    ->label('فقط تأخیردار')
                    ->query(fn ($q) => $q->where('is_overdue', true)),
                Filter::make('mine')
                    ->label('وظایف من')
                    ->query(fn ($q) => $q->forUser(auth()->user())),
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
