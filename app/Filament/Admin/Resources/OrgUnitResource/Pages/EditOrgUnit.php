<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\OrgUnitResource\Pages;

use App\Filament\Admin\Resources\OrgUnitResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOrgUnit extends EditRecord
{
    protected static string $resource = OrgUnitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
