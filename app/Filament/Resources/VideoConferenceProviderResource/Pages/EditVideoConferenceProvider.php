<?php

declare(strict_types=1);

namespace App\Filament\Resources\VideoConferenceProviderResource\Pages;

use App\Domains\VideoConference\Services\VideoConferenceProviderManager;
use App\Filament\Resources\VideoConferenceProviderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Crypt;

class EditVideoConferenceProvider extends EditRecord
{
    protected static string $resource = VideoConferenceProviderResource::class;

    /**
     * Decrypt config برای نمایش در form.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (!empty($data['config_encrypted'])) {
            try {
                $decrypted = Crypt::decryptString($data['config_encrypted']);
                $data['config'] = json_decode($decrypted, true) ?? [];
            } catch (\Throwable) {
                $data['config'] = [];
            }
        }
        return $data;
    }

    /**
     * Encrypt config قبل از ذخیره.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $config = $data['config'] ?? [];
        unset($data['config']);

        $data['config_encrypted'] = app(VideoConferenceProviderManager::class)
            ->encryptConfig($config);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
