<?php

declare(strict_types=1);

namespace App\Filament\Resources\ResolutionResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class VotesRelationManager extends RelationManager
{
    protected static string $relationship = 'votes';
    protected static ?string $title = 'آرا';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('voter.full_name')->label('رأی‌دهنده'),
                Tables\Columns\TextColumn::make('vote')
                    ->label('رأی')
                    ->badge(),
                Tables\Columns\TextColumn::make('weight')->label('وزن'),
                Tables\Columns\IconColumn::make('is_proxy')
                    ->label('تفویض شده')
                    ->state(fn ($r) => $r->isProxyVote())
                    ->boolean(),
                Tables\Columns\TextColumn::make('rationale')->label('توضیح')->limit(40),
                Tables\Columns\TextColumn::make('voted_at')->label('زمان')->dateTime('Y/m/d H:i'),
            ])
            ->defaultSort('voted_at', 'desc');
    }
}
