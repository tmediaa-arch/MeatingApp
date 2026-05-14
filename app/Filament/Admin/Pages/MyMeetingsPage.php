<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Domains\Invitations\Actions\RespondToInvitationAction;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Models\MeetingParticipant;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class MyMeetingsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;
    protected static ?string $navigationLabel = 'جلسات من';
    protected static ?string $title = 'جلسات من';
    protected static string|\UnitEnum|null $navigationGroup = 'مدیریت جلسات';
    protected static ?int $navigationSort = 3;
    protected string $view = 'filament.admin.pages.my-meetings';

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
                    ->badge(),
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
            ->recordActions([
                ActionGroup::make([
                    Action::make('view')
                        ->label('مشاهده')
                        ->icon(Heroicon::OutlinedEye)
                        ->url(fn (Meeting $record) => route('filament.admin.resources.meetings.view', $record)),
                    Action::make('accept')
                        ->label('قبول')
                        ->icon(Heroicon::OutlinedCheck)
                        ->color('success')
                        ->visible(fn (Meeting $record) => $this->canRespond($record))
                        ->action(fn (Meeting $record) => $this->respondToInvitation($record, 'accepted')),
                    Action::make('decline')
                        ->label('رد')
                        ->icon(Heroicon::OutlinedXMark)
                        ->color('danger')
                        ->schema([
                            Textarea::make('note')
                                ->label('دلیل (اختیاری)')
                                ->rows(2),
                        ])
                        ->visible(fn (Meeting $record) => $this->canRespond($record))
                        ->action(fn (Meeting $record, array $data) => $this->respondToInvitation($record, 'declined', $data['note'] ?? null)),
                    Action::make('tentative')
                        ->label('شاید')
                        ->icon(Heroicon::OutlinedQuestionMarkCircle)
                        ->color('warning')
                        ->visible(fn (Meeting $record) => $this->canRespond($record))
                        ->action(fn (Meeting $record) => $this->respondToInvitation($record, 'tentative')),
                ]),
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
