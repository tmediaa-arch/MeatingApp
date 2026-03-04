<?php

declare(strict_types=1);

namespace App\Filament\Resources\MinuteResource\RelationManagers;

use App\Domains\Resolutions\Enums\ResolutionStatus;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ResolutionsRelationManager extends RelationManager
{
    protected static string $relationship = 'resolutions';
    protected static ?string $title = 'مصوبات';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('resolution_number')->label('شماره')->searchable(),
                Tables\Columns\TextColumn::make('title')->label('عنوان')->limit(50),
                Tables\Columns\TextColumn::make('type')->label('نوع')->badge(),
                Tables\Columns\TextColumn::make('status')->label('وضعیت')->badge()
                    ->color(fn (ResolutionStatus $s) => $s->color())
                    ->formatStateUsing(fn (ResolutionStatus $s) => $s->label()),
                Tables\Columns\TextColumn::make('due_date')->label('مهلت')->date('Y/m/d'),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('مشاهده')
                    ->url(fn ($record) => route('filament.admin.resources.resolutions.view', $record)),
            ]);
    }
}
