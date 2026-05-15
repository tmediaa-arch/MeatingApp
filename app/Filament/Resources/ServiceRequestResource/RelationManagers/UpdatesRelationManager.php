<?php

declare(strict_types=1);

namespace App\Filament\Resources\ServiceRequestResource\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class UpdatesRelationManager extends RelationManager
{
    protected static string $relationship = 'updates';

    protected static ?string $title = 'تاریخچه';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('comment')
                ->label('یادداشت')
                ->required()
                ->rows(3),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('comment')
            ->columns([
                Tables\Columns\TextColumn::make('occurred_at')
                    ->label('زمان')
                    ->dateTime('Y/m/d H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('update_type')
                    ->label('نوع')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'status_change' => 'تغییر وضعیت',
                        'comment' => 'یادداشت',
                        'assignment_change' => 'تغییر ارجاع',
                        'cost_update' => 'تغییر هزینه',
                        'schedule_change' => 'تغییر زمان',
                        'attachment_added' => 'افزودن پیوست',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('from_value')->label('از')->placeholder('—'),
                Tables\Columns\TextColumn::make('to_value')->label('به')->placeholder('—'),
                Tables\Columns\TextColumn::make('comment')->label('یادداشت')->wrap()->limit(60),
                Tables\Columns\TextColumn::make('actor.name')->label('عامل')->placeholder('—'),
            ])
            ->defaultSort('occurred_at', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->label('افزودن یادداشت')
                    ->using(function (array $data) {
                        return $this->getOwnerRecord()->updates()->create([
                            'update_type' => 'comment',
                            'comment' => $data['comment'],
                            'actor_user_id' => auth()->id(),
                        ]);
                    }),
            ])
            ->recordActions([])
            ->paginated([10, 25, 50]);
    }

    public function isReadOnly(): bool
    {
        return false;
    }
}
