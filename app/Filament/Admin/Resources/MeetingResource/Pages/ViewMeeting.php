<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\MeetingResource\Pages;

use App\Domains\Meetings\Actions\CancelMeetingAction;
use App\Domains\Meetings\Actions\SendInvitationsAction;
use App\Domains\Meetings\Actions\TransitionMeetingStatusAction;
use App\Domains\Meetings\Enums\MeetingStatus;
use App\Filament\Admin\Resources\MeetingResource;
use Filament\Actions;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewMeeting extends ViewRecord
{
    protected static string $resource = MeetingResource::class;

    protected function getHeaderActions(): array
    {
        $actions = [
            Actions\EditAction::make()
                ->visible(fn () => $this->record->status->isEditable()),
        ];

        // اضافه کردن action transition براساس وضعیت
        foreach ($this->record->status->allowedTransitions() as $targetStatus) {
            $actions[] = $this->makeTransitionAction($targetStatus);
        }

        // ارسال دعوت‌نامه‌ها
        if (in_array($this->record->status, [MeetingStatus::Scheduled, MeetingStatus::InvitationsSent], true)) {
            $actions[] = Actions\Action::make('send_invitations')
                ->label('ارسال دعوت‌نامه‌ها')
                ->icon('heroicon-o-paper-airplane')
                ->color('info')
                ->requiresConfirmation()
                ->action(function () {
                    $count = app(SendInvitationsAction::class)->execute($this->record);
                    Notification::make()
                        ->title("{$count} دعوت‌نامه در صف ارسال قرار گرفت")
                        ->success()
                        ->send();
                });
        }

        return $actions;
    }

    private function makeTransitionAction(MeetingStatus $targetStatus): Actions\Action
    {
        $needsReason = in_array($targetStatus, [
            MeetingStatus::Cancelled,
            MeetingStatus::Postponed,
        ], true);

        $action = Actions\Action::make("transition_to_{$targetStatus->value}")
            ->label('انتقال به: ' . $targetStatus->label())
            ->icon($targetStatus->icon())
            ->color($targetStatus->color())
            ->requiresConfirmation()
            ->modalHeading('تأیید تغییر وضعیت');

        if ($needsReason) {
            $action->form([
                Textarea::make('reason')
                    ->label('دلیل')
                    ->required()
                    ->rows(3),
            ]);
        }

        $action->action(function (array $data = []) use ($targetStatus) {
            $reason = $data['reason'] ?? null;

            if ($targetStatus === MeetingStatus::Cancelled) {
                app(CancelMeetingAction::class)->execute($this->record, $reason);
            } else {
                app(TransitionMeetingStatusAction::class)
                    ->execute($this->record, $targetStatus, $reason);
            }

            Notification::make()
                ->title('وضعیت جلسه تغییر کرد')
                ->success()
                ->send();
        });

        return $action;
    }

    public function getTitle(): string
    {
        return sprintf('%s — %s', $this->record->meeting_number, $this->record->subject);
    }
}
