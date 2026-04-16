<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\DelegationResource\Pages;

use App\Filament\Admin\Resources\DelegationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDelegations extends ListRecords
{
    protected static string $resource = DelegationResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
