<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\MeetingResource\Pages;

use App\Domains\Meetings\Actions\UpdateMeetingAction;
use App\Filament\Admin\Resources\MeetingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMeeting extends EditRecord
{
    protected static string $resource = MeetingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->visible(fn () => $this->record->status === \App\Domains\Meetings\Enums\MeetingStatus::Draft),
        ];
    }

    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        return app(UpdateMeetingAction::class)->execute($record, $data);
    }

    public function getTitle(): string
    {
        return sprintf('ویرایش جلسه: %s', $this->record->meeting_number);
    }
}
