<?php

declare(strict_types=1);

namespace App\Filament\Resources\MinuteResource\Pages;

use App\Filament\Resources\MinuteResource;
use Filament\Resources\Pages\ListRecords;

class ListMinutes extends ListRecords
{
    protected static string $resource = MinuteResource::class;
}
