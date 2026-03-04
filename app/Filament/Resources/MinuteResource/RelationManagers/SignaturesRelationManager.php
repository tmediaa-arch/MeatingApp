<?php

declare(strict_types=1);

namespace App\Filament\Resources\MinuteResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class SignaturesRelationManager extends RelationManager
{
    protected static string $relationship = 'signatures';
    protected static ?string $title = 'امضاها';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('signer.name')->label('امضاکننده'),
                Tables\Columns\TextColumn::make('signer_role')->label('نقش')->badge()->formatStateUsing(fn ($state) => match ($state) {
                    'secretary' => 'دبیر',
                    'chairperson' => 'رئیس',
                    default => 'سایر',
                }),
                Tables\Columns\TextColumn::make('signature_method')->label('روش امضا'),
                Tables\Columns\TextColumn::make('content_hash')->label('Hash محتوا')->limit(16)->tooltip(fn ($state) => $state),
                Tables\Columns\IconColumn::make('valid')->label('اعتبار')->state(fn ($record) => $record->isValidForCurrentContent())->boolean(),
                Tables\Columns\TextColumn::make('signed_at')->label('تاریخ')->dateTime('Y/m/d H:i'),
            ])
            ->defaultSort('signed_at', 'desc');
    }
}
