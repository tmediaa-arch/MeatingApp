<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SecurityEventResource\Pages;

use App\Filament\Admin\Resources\SecurityEventResource;
use Filament\Resources\Pages\ListRecords;

class ListSecurityEvents extends ListRecords
{
    protected static string $resource = SecurityEventResource::class;
}
