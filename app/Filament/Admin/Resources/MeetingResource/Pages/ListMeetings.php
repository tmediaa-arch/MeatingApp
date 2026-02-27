<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\MeetingResource\Pages;

use App\Filament\Admin\Resources\MeetingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMeetings extends ListRecords
{
    protected static string $resource = MeetingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('ایجاد جلسه جدید'),
        ];
    }
}
