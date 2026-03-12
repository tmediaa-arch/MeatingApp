<?php
declare(strict_types=1);
namespace App\Filament\Resources\TaskResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AttachmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'attachments';
    protected static ?string $title = 'پیوست‌ها';

    public function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('title')->label('عنوان')->searchable(),
            Tables\Columns\TextColumn::make('file_name')->label('نام فایل'),
            Tables\Columns\TextColumn::make('file_size_human')->label('حجم'),
            Tables\Columns\TextColumn::make('uploadedBy.name')->label('بارگذار'),
            Tables\Columns\TextColumn::make('uploaded_at')->label('زمان')->dateTime('Y/m/d H:i'),
        ]);
    }
}
