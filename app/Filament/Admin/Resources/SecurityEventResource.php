<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domains\Audit\Models\SecurityEvent;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class SecurityEventResource extends Resource
{
    protected static ?string $model = SecurityEvent::class;
    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';
    protected static ?string $navigationGroup = 'ممیزی و امنیت';
    protected static ?int $navigationSort = 85;

    public static function getModelLabel(): string
    {
        return 'رویداد امنیتی';
    }

    public static function getPluralModelLabel(): string
    {
        return 'رویدادهای امنیتی';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('detected_at')
                    ->label('زمان')
                    ->dateTime('Y/m/d H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('event')
                    ->label('رویداد')
                    ->badge()
                    ->searchable(),

                Tables\Columns\TextColumn::make('severity')
                    ->label('شدت')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'critical' => 'danger',
                        'warning' => 'warning',
                        'notice' => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('description')
                    ->label('توضیح')
                    ->limit(80),

                Tables\Columns\TextColumn::make('user.username')
                    ->label('کاربر مرتبط')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('reviewed')
                    ->label('بررسی شده')
                    ->boolean()
                    ->getStateUsing(fn (SecurityEvent $r) => $r->reviewed_at !== null),
            ])
            ->defaultSort('detected_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('severity')
                    ->label('شدت')
                    ->options([
                        'critical' => 'بحرانی',
                        'warning' => 'هشدار',
                        'notice' => 'اطلاع',
                    ]),

                Tables\Filters\Filter::make('unreviewed')
                    ->label('بررسی نشده')
                    ->query(fn ($q) => $q->whereNull('reviewed_at')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('review')
                    ->label('ثبت بررسی')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (SecurityEvent $r) => $r->reviewed_at === null)
                    ->form([
                        Forms\Components\Textarea::make('review_notes')
                            ->label('یادداشت بررسی')
                            ->required(),
                    ])
                    ->action(function (SecurityEvent $record, array $data) {
                        $record->update([
                            'reviewed_by' => auth()->id(),
                            'reviewed_at' => now(),
                            'review_notes' => $data['review_notes'],
                        ]);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Admin\Resources\SecurityEventResource\Pages\ListSecurityEvents::route('/'),
            'view' => \App\Filament\Admin\Resources\SecurityEventResource\Pages\ViewSecurityEvent::route('/{record}'),
        ];
    }
}
