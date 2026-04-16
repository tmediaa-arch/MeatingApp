<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\OrgUnitResource\Pages;

use App\Domains\Organization\Actions\CreateOrgUnitAction;
use App\Domains\Organization\Enums\OrgUnitType;
use App\Domains\Organization\Models\Organization;
use App\Domains\Organization\Models\OrgUnit;
use App\Filament\Admin\Resources\OrgUnitResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOrgUnit extends CreateRecord
{
    protected static string $resource = OrgUnitResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $organization = Organization::findOrFail($data['organization_id']);
        $parent = !empty($data['parent_id']) ? OrgUnit::find($data['parent_id']) : null;

        return app(CreateOrgUnitAction::class)->execute(
            organization: $organization,
            code: $data['code'],
            name: $data['name'],
            type: OrgUnitType::from($data['type']),
            parent: $parent,
            shortName: $data['short_name'] ?? null,
            englishName: $data['english_name'] ?? null,
            managerEmployeeId: $data['manager_employee_id'] ?? null,
            additionalData: array_intersect_key($data, array_flip([
                'phone', 'email', 'address',
                'location_floor', 'location_building',
                'display_order',
            ])),
        );
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
