<?php

declare(strict_types=1);

namespace App\Domains\Organization\Actions;

use App\Domains\Organization\Enums\OrgUnitType;
use App\Domains\Organization\Models\OrgUnit;
use App\Domains\Organization\Models\Organization;
use Illuminate\Support\Facades\DB;

/**
 * CreateOrgUnitAction — ساخت یک واحد سازمانی جدید.
 *
 * level و path توسط OrgUnitObserver به‌صورت خودکار مقداردهی می‌شوند.
 */
class CreateOrgUnitAction
{
    /**
     * @param array<string, mixed> $additionalData
     */
    public function execute(
        Organization $organization,
        string $code,
        string $name,
        OrgUnitType $type,
        ?OrgUnit $parent = null,
        ?string $shortName = null,
        ?string $englishName = null,
        ?int $managerEmployeeId = null,
        array $additionalData = [],
    ): OrgUnit {
        return DB::transaction(function () use (
            $organization, $code, $name, $type, $parent,
            $shortName, $englishName, $managerEmployeeId, $additionalData,
        ) {
            $allowed = array_intersect_key($additionalData, array_flip([
                'phone', 'email', 'address',
                'location_floor', 'location_building', 'display_order',
            ]));

            return OrgUnit::create(array_merge([
                'organization_id' => $organization->id,
                'parent_id' => $parent?->id,
                'code' => $code,
                'name' => $name,
                'short_name' => $shortName,
                'english_name' => $englishName,
                'type' => $type,
                'manager_employee_id' => $managerEmployeeId,
                'is_active' => true,
            ], $allowed));
        });
    }
}
