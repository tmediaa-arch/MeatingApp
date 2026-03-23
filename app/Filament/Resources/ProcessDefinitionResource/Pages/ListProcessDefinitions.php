<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProcessDefinitionResource\Pages;

use App\Filament\Resources\ProcessDefinitionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProcessDefinitions extends ListRecords
{
    protected static string $resource = ProcessDefinitionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('فرایند جدید'),
        ];
    }
}
