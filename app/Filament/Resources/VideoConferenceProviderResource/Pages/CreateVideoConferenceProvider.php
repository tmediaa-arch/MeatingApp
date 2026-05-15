<?php

declare(strict_types=1);

namespace App\Filament\Resources\VideoConferenceProviderResource\Pages;

use App\Domains\VideoConference\Services\VideoConferenceProviderManager;
use App\Filament\Resources\VideoConferenceProviderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateVideoConferenceProvider extends CreateRecord
{
    protected static string $resource = VideoConferenceProviderResource::class;

    protected function mutateDataBeforeCreate(array $data): array
    {
        $config = $data['config'] ?? [];
        unset($data['config']);

        $data['config_encrypted'] = app(VideoConferenceProviderManager::class)
            ->encryptConfig($config);

        return $data;
    }
}
