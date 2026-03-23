<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProcessDefinitionResource\Pages;

use App\Filament\Resources\ProcessDefinitionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProcessDefinition extends EditRecord
{
    protected static string $resource = ProcessDefinitionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
