<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\RoleResource\Pages;

use App\Filament\Admin\Resources\RoleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    protected function mutateDataBeforeCreate(array $data): array
    {
        $data['guard_name'] = 'web';
        $data['is_system'] = false;
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
