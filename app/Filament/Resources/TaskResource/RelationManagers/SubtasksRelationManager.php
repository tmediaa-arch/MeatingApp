<?php
declare(strict_types=1);
namespace App\Filament\Resources\TaskResource\RelationManagers;

use App\Domains\Tasks\Enums\TaskStatus;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class SubtasksRelationManager extends RelationManager
{
    protected static string $relationship = 'subtasks';
    protected static ?string $title = 'زیر-وظایف';

    public function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('task_number')->label('شماره')->searchable(),
            Tables\Columns\TextColumn::make('title')->label('عنوان')->limit(40),
            Tables\Columns\TextColumn::make('assignee.full_name')->label('مجری'),
            Tables\Columns\TextColumn::make('status')
                ->label('وضعیت')
                ->badge()
                ->color(fn (TaskStatus $s) => $s->color())
                ->formatStateUsing(fn (TaskStatus $s) => $s->label()),
            Tables\Columns\TextColumn::make('due_date')->label('مهلت')->date('Y/m/d'),
        ]);
    }
}
