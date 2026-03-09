<?php
declare(strict_types=1);
namespace App\Filament\Resources\ResolutionResource\Pages;

use App\Domains\Resolutions\Actions\CastVoteAction;
use App\Domains\Resolutions\Actions\CloseVotingAction;
use App\Domains\Resolutions\Actions\CreateTasksFromResolutionAction;
use App\Domains\Resolutions\Actions\TransitionResolutionStatusAction;
use App\Domains\Resolutions\Enums\ResolutionStatus;
use App\Domains\Resolutions\Enums\VoteValue;
use App\Filament\Resources\ResolutionResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewResolution extends ViewRecord
{
    protected static string $resource = ResolutionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),

            Actions\Action::make('openVoting')
                ->label('شروع رأی‌گیری')
                ->icon('heroicon-o-hand-raised')
                ->color('info')
                ->visible(fn () => $this->record->requires_voting
                    && !$this->record->voting_opened_at
                    && $this->record->status === ResolutionStatus::Draft)
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update(['voting_opened_at' => now()]);
                    Notification::make()->success()->title('رأی‌گیری شروع شد')->send();
                }),

            Actions\Action::make('castVote')
                ->label('ثبت رأی من')
                ->icon('heroicon-o-pencil')
                ->color('primary')
                ->visible(fn () => auth()->user()->can('vote', $this->record))
                ->form([
                    Forms\Components\Select::make('vote')
                        ->label('رأی')
                        ->options(VoteValue::options())
                        ->required(),
                    Forms\Components\Textarea::make('rationale')->label('توضیح اختیاری')->rows(2),
                ])
                ->action(function (array $data) {
                    app(CastVoteAction::class)->execute(
                        $this->record,
                        auth()->user(),
                        VoteValue::from($data['vote']),
                        $data['rationale'] ?? null,
                    );
                    Notification::make()->success()->title('رأی شما ثبت شد')->send();
                }),

            Actions\Action::make('closeVoting')
                ->label('بستن رأی‌گیری')
                ->icon('heroicon-o-lock-closed')
                ->color('warning')
                ->visible(fn () => auth()->user()->can('closeVoting', $this->record))
                ->requiresConfirmation()
                ->action(function () {
                    app(CloseVotingAction::class)->execute($this->record);
                    Notification::make()->success()->title('رأی‌گیری بسته شد')->send();
                }),

            Actions\Action::make('startExecution')
                ->label('شروع اجرا')
                ->icon('heroicon-o-play')
                ->color('success')
                ->visible(fn () => $this->record->status === ResolutionStatus::Approved)
                ->requiresConfirmation()
                ->action(function () {
                    app(TransitionResolutionStatusAction::class)->execute(
                        $this->record,
                        ResolutionStatus::InExecution,
                        'شروع اجرای مصوبه',
                    );
                    // تولید Tasks
                    $tasks = app(CreateTasksFromResolutionAction::class)->execute($this->record);
                    Notification::make()
                        ->success()
                        ->title('اجرا شروع شد')
                        ->body(sprintf('%d وظیفه ایجاد شد', count($tasks)))
                        ->send();
                }),

            Actions\Action::make('markCompleted')
                ->label('اعلام اتمام')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn () => $this->record->status === ResolutionStatus::InExecution)
                ->requiresConfirmation()
                ->action(function () {
                    app(TransitionResolutionStatusAction::class)->execute(
                        $this->record,
                        ResolutionStatus::Completed,
                        'تأیید اتمام اجرا',
                    );
                    Notification::make()->success()->title('مصوبه به‌عنوان اجرا شده ثبت شد')->send();
                }),
        ];
    }
}
