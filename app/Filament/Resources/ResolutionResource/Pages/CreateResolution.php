<?php
declare(strict_types=1);
namespace App\Filament\Resources\ResolutionResource\Pages;

use App\Domains\Resolutions\Actions\CreateResolutionAction;
use App\Domains\Minutes\Models\Minute;
use App\Filament\Resources\ResolutionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateResolution extends CreateRecord
{
    protected static string $resource = ResolutionResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $minute = Minute::findOrFail($data['minute_id']);
        return app(CreateResolutionAction::class)->execute($minute, $data);
    }
}
