<?php

declare(strict_types=1);

namespace App\Domains\Organization\Observers;

use App\Domains\Organization\Models\OrgUnit;

/**
 * OrgUnitObserver — نگهداری خودکار level و materialized path.
 *
 * path به‌صورت "1/5/12" ذخیره می‌شود (زنجیره id ها تا خود واحد).
 */
class OrgUnitObserver
{
    public function creating(OrgUnit $unit): void
    {
        if ($unit->level === null) {
            $parent = $unit->parent_id ? OrgUnit::find($unit->parent_id) : null;
            $unit->level = $parent ? (int) ($parent->level ?? 0) + 1 : 0;
        }
    }

    public function created(OrgUnit $unit): void
    {
        if (empty($unit->path)) {
            $parent = $unit->parent_id ? OrgUnit::find($unit->parent_id) : null;
            $unit->path = $parent && $parent->path
                ? $parent->path . '/' . $unit->id
                : (string) $unit->id;
            $unit->saveQuietly();
        }
    }
}
