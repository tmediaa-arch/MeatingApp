<?php
declare(strict_types=1);
namespace App\Filament\Resources\ResolutionResource\Pages;

use App\Filament\Resources\ResolutionResource;
use Filament\Resources\Pages\ListRecords;

class ListResolutions extends ListRecords
{
    protected static string $resource = ResolutionResource::class;
}
