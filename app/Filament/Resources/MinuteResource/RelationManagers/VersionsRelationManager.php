<?php

declare(strict_types=1);

namespace App\Filament\Resources\MinuteResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class VersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'versions';
    protected static ?string $title = 'تاریخچه نسخه‌ها';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('version_number')->label('نسخه')->sortable(),
                Tables\Columns\TextColumn::make('change_summary')->label('خلاصه تغییر')->limit(60),
                Tables\Columns\TextColumn::make('creator.name')->label('توسط'),
                Tables\Columns\TextColumn::make('created_at')->label('تاریخ')->dateTime('Y/m/d H:i'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->modalContent(fn ($record) => view('filament.minute-version-content', [
                    'version' => $record,
                ]))->modalHeading(fn ($record) => "نسخه #{$record->version_number}"),
            ])
            ->defaultSort('version_number', 'desc');
    }
}
