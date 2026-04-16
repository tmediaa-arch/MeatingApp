<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\OrgUnitResource\Pages;

use App\Filament\Admin\Resources\OrgUnitResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewOrgUnit extends ViewRecord
{
    protected static string $resource = OrgUnitResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\EditAction::make()];
    }
}
