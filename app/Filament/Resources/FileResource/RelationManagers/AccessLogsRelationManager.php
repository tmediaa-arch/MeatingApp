<?php

declare(strict_types=1);

namespace App\Filament\Resources\FileResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AccessLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'accessLogs';

    protected static ?string $title = 'لاگ دسترسی';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('accessed_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('accessed_at')
                    ->label('زمان')
                    ->dateTime('Y/m/d H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.display_name')->label('کاربر')->placeholder('—'),
                Tables\Columns\TextColumn::make('action')->label('عملیات')->badge(),
                Tables\Columns\TextColumn::make('ip_address')->label('IP')->placeholder('—'),
            ]);
    }
}
