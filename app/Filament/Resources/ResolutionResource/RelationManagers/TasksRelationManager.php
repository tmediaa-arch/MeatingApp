<?php
declare(strict_types=1);
namespace App\Filament\Resources\ResolutionResource\RelationManagers;

use App\Domains\Tasks\Enums\TaskStatus;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class TasksRelationManager extends RelationManager
{
    protected static string $relationship = 'tasks';
    protected static ?string $title = 'وظایف';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('task_number')->label('شماره')->searchable(),
                Tables\Columns\TextColumn::make('title')->label('عنوان')->limit(40),
                Tables\Columns\TextColumn::make('assignee.full_name')->label('مجری'),
                Tables\Columns\TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge()
                    ->color(fn (TaskStatus $s) => $s->color())
                    ->formatStateUsing(fn (TaskStatus $s) => $s->label()),
                Tables\Columns\ProgressColumn::make('progress_percent')->label('پیشرفت'),
                Tables\Columns\TextColumn::make('due_date')->label('مهلت')->date('Y/m/d'),
            ]);
    }
}
