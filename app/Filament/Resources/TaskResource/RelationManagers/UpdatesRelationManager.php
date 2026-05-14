<?php

declare(strict_types=1);

namespace App\Filament\Resources\TaskResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class UpdatesRelationManager extends RelationManager
{
    protected static string $relationship = 'updates';
    protected static ?string $title = 'تاریخچه';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('occurred_at')->label('زمان')->dateTime('Y/m/d H:i'),
                Tables\Columns\TextColumn::make('updater.name')->label('توسط'),
                Tables\Columns\TextColumn::make('update_type')
                    ->label('نوع')
                    ->badge(),
                Tables\Columns\TextColumn::make('content')->label('توضیحات')->limit(80),
                Tables\Columns\TextColumn::make('status_change')
                    ->label('تغییر وضعیت')
                    ->state(fn ($r) => $r->old_status && $r->new_status
                        ? "{$r->old_status} → {$r->new_status}"
                        : null),
            ])
            ->defaultSort('occurred_at', 'desc');
    }
}
