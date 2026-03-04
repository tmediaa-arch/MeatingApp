<?php

declare(strict_types=1);

namespace App\Filament\Resources\MinuteResource\Pages;

use App\Domains\Minutes\Actions\UpdateMinuteAction;
use App\Filament\Resources\MinuteResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMinute extends EditRecord
{
    protected static string $resource = MinuteResource::class;

    protected function handleRecordUpdate($record, array $data): \Illuminate\Database\Eloquent\Model
    {
        return app(UpdateMinuteAction::class)->execute($record, $data);
    }

    protected function getHeaderActions(): array
    {
        return [Actions\ViewAction::make()];
    }
}
