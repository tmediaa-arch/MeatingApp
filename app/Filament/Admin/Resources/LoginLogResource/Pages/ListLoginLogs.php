<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\LoginLogResource\Pages;

use App\Filament\Admin\Resources\LoginLogResource;
use Filament\Resources\Pages\ListRecords;

class ListLoginLogs extends ListRecords
{
    protected static string $resource = LoginLogResource::class;
}
