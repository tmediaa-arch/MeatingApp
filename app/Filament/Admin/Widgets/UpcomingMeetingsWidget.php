<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Domains\Calendar\Services\JalaliCalendarService;
use App\Domains\Meetings\Models\Meeting;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class UpcomingMeetingsWidget extends BaseWidget
{
    protected static ?string $heading = 'جلسات آینده من';
    protected int|string|array $columnSpan = 'full';
    protected static ?int $sort = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Meeting::query()
                    ->forUser(auth()->user())
                    ->upcoming()
                    ->limit(5)
            )
            ->columns([
                TextColumn::make('meeting_number')->label('شماره'),
                TextColumn::make('subject')
                    ->label('موضوع')
                    ->limit(40)
                    ->wrap(),
                TextColumn::make('scheduled_start_at')
                    ->label('زمان')
                    ->formatStateUsing(fn ($state) => app(JalaliCalendarService::class)
                        ->formatHuman($state)),
                TextColumn::make('room.name')
                    ->label('سالن')
                    ->default('—'),
                TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge()
                    ->color(fn ($s) => $s->color())
                    ->formatStateUsing(fn ($s) => $s->label()),
            ])
            ->paginated(false)
            ->emptyStateHeading('جلسه‌ای در آینده ندارید')
            ->emptyStateIcon('heroicon-o-calendar');
    }
}
