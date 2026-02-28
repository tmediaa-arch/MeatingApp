<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Domains\Invitations\Actions\RespondToInvitationAction;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Models\MeetingParticipant;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class MyMeetingsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';
    protected static ?string $navigationLabel = 'جلسات من';
    protected static ?string $title = 'جلسات من';
    protected static ?string $navigationGroup = 'مدیریت جلسات';
    protected static ?int $navigationSort = 3;
    protected static string $view = 'filament.admin.pages.my-meetings';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Meeting::query()
                    ->forUser(auth()->user())
                    ->upcoming()
                    ->with(['room', 'chairperson', 'secretary'])
            )
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('meeting_number')
                    ->label('شماره')
                    ->searchable(),
                \Filament\Tables\Columns\TextColumn::make('subject')
                    ->label('موضوع')
                    ->wrap()
                    ->limit(60),
                \Filament\Tables\Columns\TextColumn::make('scheduled_start_at')
                    ->label('زمان')
                    ->formatStateUsing(fn ($state) => app(\App\Domains\Calendar\Services\JalaliCalendarService::class)
                        ->formatHuman($state))
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('room.name')
                    ->label('سالن')
                    ->default('—'),
                \Filament\Tables\Columns\TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge()
                    ->color(fn ($state) => $state->color())
                    ->formatStateUsing(fn ($state) => $state->label()),
                \Filament\Tables\Columns\TextColumn::make('my_invitation_status')
                    ->label('پاسخ من')
                    ->getStateUsing(function (Meeting $record) {
                        $emp = auth()->user()->employee;
                        if (!$emp) return '—';
                        $p = $record->participants()->where('employee_id', $emp->id)->first();
                        return $p?->invitation_status?->label() ?? '—';
                    }),
            ])
            ->defaultSort('scheduled_start_at', 'asc')
            ->actions([
                \Filament\Tables\Actions\Action::make('view')
                    ->label('مشاهده')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Meeting $record) => route('filament.admin.resources.meetings.view', $record)),
                \Filament\Tables\Actions\Action::make('accept')
                    ->label('قبول')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (Meeting $record) => $this->canRespond($record))
                    ->action(fn (Meeting $record) => $this->respondToInvitation($record, 'accepted')),
                \Filament\Tables\Actions\Action::make('decline')
                    ->label('رد')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->form([
                        Textarea::make('note')
                            ->label('دلیل (اختیاری)')
                            ->rows(2),
                    ])
                    ->visible(fn (Meeting $record) => $this->canRespond($record))
                    ->action(fn (Meeting $record, array $data) => $this->respondToInvitation($record, 'declined', $data['note'] ?? null)),
                \Filament\Tables\Actions\Action::make('tentative')
                    ->label('شاید')
                    ->icon('heroicon-o-question-mark-circle')
                    ->color('warning')
                    ->visible(fn (Meeting $record) => $this->canRespond($record))
                    ->action(fn (Meeting $record) => $this->respondToInvitation($record, 'tentative')),
            ]);
    }

    private function canRespond(Meeting $meeting): bool
    {
        $emp = auth()->user()->employee;
        if (!$emp) return false;

        $p = $meeting->participants()->where('employee_id', $emp->id)->first();
        return $p?->canRespondInvitation() ?? false;
    }

    private function respondToInvitation(Meeting $meeting, string $response, ?string $note = null): void
    {
        $emp = auth()->user()->employee;
        $participant = $meeting->participants()->where('employee_id', $emp->id)->firstOrFail();

        app(RespondToInvitationAction::class)->execute(
            participant: $participant,
            response: $response,
            note: $note,
            respondedByUserId: auth()->id(),
        );

        Notification::make()
            ->title('پاسخ شما ثبت شد')
            ->success()
            ->send();
    }
}
