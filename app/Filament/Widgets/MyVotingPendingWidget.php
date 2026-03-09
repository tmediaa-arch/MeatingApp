<?php
declare(strict_types=1);
namespace App\Filament\Widgets;

use App\Domains\Resolutions\Models\Resolution;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class MyVotingPendingWidget extends BaseWidget
{
    protected static ?string $heading = 'مصوبات منتظر رأی من';
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $userEmployeeId = auth()->user()->employee_id;

        return $table
            ->query(
                Resolution::query()
                    ->where('requires_voting', true)
                    ->whereNotNull('voting_opened_at')
                    ->whereNull('voting_closed_at')
                    ->whereHas('meeting.participants', fn ($q) => $q->where('employee_id', $userEmployeeId))
                    ->whereDoesntHave('votes', fn ($q) => $q->where('voter_employee_id', $userEmployeeId))
            )
            ->columns([
                Tables\Columns\TextColumn::make('resolution_number')->label('شماره')->searchable(),
                Tables\Columns\TextColumn::make('title')->label('عنوان')->limit(40),
                Tables\Columns\TextColumn::make('voting_opened_at')->label('شروع رأی‌گیری')->dateTime('Y/m/d H:i'),
                Tables\Columns\TextColumn::make('voting_progress_text')
                    ->label('پیشرفت')
                    ->state(fn ($r) => "{$r->voters_total} رأی"),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('مشاهده و رأی')
                    ->color('primary')
                    ->url(fn ($record) => route('filament.admin.resources.resolutions.view', $record)),
            ]);
    }
}
