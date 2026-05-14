<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Domains\Organization\Models\OrgUnit;
use App\Domains\Organization\Models\Organization;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class OrgChartPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedShare;
    protected static ?string $navigationGroup = 'ساختار سازمانی';
    protected static ?int $navigationSort = 25;
    protected string $view = 'filament.admin.pages.org-chart';

    public ?int $selectedOrganization = null;

    public function mount(): void
    {
        $this->selectedOrganization = Organization::first()?->id;
    }

    public function getTitle(): string
    {
        return 'نمودار سازمانی';
    }

    public function getOrganizations(): \Illuminate\Support\Collection
    {
        return Organization::where('is_active', true)->get();
    }

    public function getTreeData(): array
    {
        if (! $this->selectedOrganization) {
            return [];
        }

        $units = OrgUnit::query()
            ->where('organization_id', $this->selectedOrganization)
            ->where('is_active', true)
            ->with(['manager', 'children' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        $byParent = $units->groupBy('parent_id');
        $roots = $byParent->get(null, collect());

        $buildNode = function ($unit) use (&$buildNode, $byParent) {
            return [
                'id' => $unit->id,
                'name' => $unit->name,
                'code' => $unit->code,
                'type' => $unit->type->label(),
                'manager' => $unit->manager?->full_name,
                'employees_count' => $unit->employees()->count(),
                'children' => $byParent->get($unit->id, collect())
                    ->map(fn ($child) => $buildNode($child))
                    ->toArray(),
            ];
        };

        return $roots->map(fn ($r) => $buildNode($r))->toArray();
    }
}
