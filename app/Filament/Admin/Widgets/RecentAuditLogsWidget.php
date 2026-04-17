<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Domains\Audit\Models\AuditLog;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentAuditLogsWidget extends BaseWidget
{
    protected static ?string $heading = 'آخرین لاگ‌های ممیزی';
    protected static ?int $sort = 2;
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(AuditLog::query()->orderByDesc('performed_at')->limit(10))
            ->columns([
                Tables\Columns\TextColumn::make('performed_at')
                    ->label('زمان')
                    ->dateTime('Y/m/d H:i:s'),

                Tables\Columns\TextColumn::make('user_display_name')
                    ->label('کاربر'),

                Tables\Columns\TextColumn::make('event')
                    ->label('رویداد')
                    ->badge(),

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
                    ->limit(60),
            ])
            ->paginated(false);
    }
}
