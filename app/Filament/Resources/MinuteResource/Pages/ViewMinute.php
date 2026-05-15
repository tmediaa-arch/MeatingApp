<?php

declare(strict_types=1);

namespace App\Filament\Resources\MinuteResource\Pages;

use App\Domains\Minutes\Actions\PublishMinuteAction;
use App\Domains\Minutes\Actions\SignMinuteAction;
use App\Domains\Minutes\Actions\TransitionMinuteStatusAction;
use App\Domains\Minutes\Enums\MinuteStatus;
use App\Filament\Resources\MinuteResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewMinute extends ViewRecord
{
    protected static string $resource = MinuteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn () => $this->record->status->isEditable()),

            Actions\Action::make('sendForReview')
                ->label('ارسال برای بازبینی')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->status === MinuteStatus::Draft)
                ->action(function () {
                    app(TransitionMinuteStatusAction::class)
                        ->execute($this->record, MinuteStatus::Review, 'ارسال برای بازبینی');
                    Notification::make()->success()->title('ارسال شد')->send();
                }),

            Actions\Action::make('signAsSecretary')
                ->label('امضای دبیر')
                ->icon(Heroicon::OutlinedFingerPrint)
                ->color('info')
                ->requiresConfirmation()
                ->visible(fn () => auth()->user()->can('signAsSecretary', $this->record))
                ->action(function () {
                    app(SignMinuteAction::class)
                        ->execute($this->record, auth()->user(), 'secretary');
                    Notification::make()->success()->title('امضا ثبت شد')->send();
                }),

            Actions\Action::make('signAsChairperson')
                ->label('امضای رئیس')
                ->icon(Heroicon::OutlinedFingerPrint)
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn () => auth()->user()->can('signAsChairperson', $this->record))
                ->action(function () {
                    app(SignMinuteAction::class)
                        ->execute($this->record, auth()->user(), 'chairperson');
                    Notification::make()->success()->title('امضا ثبت شد')->send();
                }),

            Actions\Action::make('publish')
                ->label('انتشار')
                ->icon(Heroicon::OutlinedCheckBadge)
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('انتشار صورتجلسه')
                ->modalDescription('PDF تولید می‌شود و به همه شرکت‌کنندگان اطلاع داده می‌شود.')
                ->visible(fn () => auth()->user()->can('publish', $this->record))
                ->action(function () {
                    app(PublishMinuteAction::class)->execute($this->record, auth()->user());
                    Notification::make()->success()->title('صورتجلسه منتشر شد')->send();
                }),

            Actions\Action::make('downloadPdf')
                ->label('دانلود PDF')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->visible(fn () => $this->record->pdf_path !== null)
                ->action(function () {
                    return response()->download(
                        storage_path('app/' . $this->record->pdf_path),
                        $this->record->minute_number . '.pdf',
                    );
                }),
        ];
    }
}
