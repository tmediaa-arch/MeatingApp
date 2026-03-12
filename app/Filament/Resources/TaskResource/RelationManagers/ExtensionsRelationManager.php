<?php
declare(strict_types=1);
namespace App\Filament\Resources\TaskResource\RelationManagers;

use App\Domains\Tasks\Actions\ReviewExtensionAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ExtensionsRelationManager extends RelationManager
{
    protected static string $relationship = 'extensions';
    protected static ?string $title = 'درخواست‌های تمدید';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('requester.name')->label('درخواست‌دهنده'),
                Tables\Columns\TextColumn::make('original_due_date')->label('مهلت اولیه')->date('Y/m/d'),
                Tables\Columns\TextColumn::make('requested_due_date')->label('مهلت درخواستی')->date('Y/m/d'),
                Tables\Columns\TextColumn::make('reason')->label('دلیل')->limit(60),
                Tables\Columns\TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('reviewer.name')->label('بررسی‌کننده'),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('تأیید')
                    ->color('success')
                    ->icon('heroicon-o-check')
                    ->visible(fn ($record) => $record->isPending())
                    ->form([
                        Forms\Components\Textarea::make('note')->label('یادداشت')->rows(2),
                    ])
                    ->action(function ($record, array $data) {
                        app(ReviewExtensionAction::class)->execute(
                            $record, auth()->user(), true, $data['note'] ?? null,
                        );
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('رد')
                    ->color('danger')
                    ->icon('heroicon-o-x-mark')
                    ->visible(fn ($record) => $record->isPending())
                    ->form([
                        Forms\Components\Textarea::make('note')->label('علت رد')->required()->rows(2),
                    ])
                    ->action(function ($record, array $data) {
                        app(ReviewExtensionAction::class)->execute(
                            $record, auth()->user(), false, $data['note'],
                        );
                    }),
            ]);
    }
}
