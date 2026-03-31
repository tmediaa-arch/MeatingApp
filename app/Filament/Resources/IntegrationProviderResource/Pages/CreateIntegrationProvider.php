<?php

declare(strict_types=1);

namespace App\Filament\Resources\IntegrationProviderResource\Pages;

use App\Filament\Resources\IntegrationProviderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateIntegrationProvider extends CreateRecord
{
    protected static string $resource = IntegrationProviderResource::class;
}
