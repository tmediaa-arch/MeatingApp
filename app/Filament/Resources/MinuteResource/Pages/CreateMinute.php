<?php

declare(strict_types=1);

namespace App\Filament\Resources\MinuteResource\Pages;

use App\Filament\Resources\MinuteResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMinute extends CreateRecord
{
    protected static string $resource = MinuteResource::class;
}
