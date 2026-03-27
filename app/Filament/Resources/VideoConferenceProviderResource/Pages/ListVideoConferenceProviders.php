<?php

declare(strict_types=1);

namespace App\Filament\Resources\VideoConferenceProviderResource\Pages;

use App\Filament\Resources\VideoConferenceProviderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVideoConferenceProviders extends ListRecords
{
    protected static string $resource = VideoConferenceProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
