<?php
declare(strict_types=1);
namespace App\Filament\Resources\ResolutionResource\RelationManagers;

use App\Domains\Resolutions\Enums\AssigneeRole;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AssigneesRelationManager extends RelationManager
{
    protected static string $relationship = 'assignees';
    protected static ?string $title = 'ذی‌ربطان';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('display_name')->label('نام'),
                Tables\Columns\TextColumn::make('role')->label('نقش')->badge()
                    ->formatStateUsing(fn (AssigneeRole $r) => $r->label()),
                Tables\Columns\IconColumn::make('is_primary')->label('اصلی')->boolean(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->form([
                        Forms\Components\Select::make('employee_id')
                            ->label('کارمند')
                            ->relationship('employee', 'first_name')
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('role')
                            ->label('نقش')
                            ->options(AssigneeRole::options())
                            ->required(),
                        Forms\Components\Toggle::make('is_primary')->label('اصلی'),
                    ]),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
