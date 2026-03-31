<?php

declare(strict_types=1);

namespace App\Filament\Resources\IntegrationProviderResource\Pages;

use App\Filament\Resources\IntegrationProviderResource;
use Filament\Resources\Pages\EditRecord;

class EditIntegrationProvider extends EditRecord
{
    protected static string $resource = IntegrationProviderResource::class;
}
