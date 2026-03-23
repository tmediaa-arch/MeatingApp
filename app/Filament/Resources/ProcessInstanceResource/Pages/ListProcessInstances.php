<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProcessInstanceResource\Pages;

use App\Filament\Resources\ProcessInstanceResource;
use Filament\Resources\Pages\ListRecords;

class ListProcessInstances extends ListRecords
{
    protected static string $resource = ProcessInstanceResource::class;
}
