<?php

declare(strict_types=1);

namespace App\Filament\Resources\IntegrationProviderResource\Pages;

use App\Filament\Resources\IntegrationProviderResource;
use Filament\Resources\Pages\ListRecords;

class ListIntegrationProviders extends ListRecords
{
    protected static string $resource = IntegrationProviderResource::class;
}
