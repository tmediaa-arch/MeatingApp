<?php
declare(strict_types=1);
namespace App\Filament\Resources\TaskResource\Pages;

use App\Domains\Tasks\Actions\CreateTaskAction;
use App\Filament\Resources\TaskResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTask extends CreateRecord
{
    protected static string $resource = TaskResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $data['organization_id'] = $data['organization_id'] ?? auth()->user()->organization_id ?? 1;
        $data['creator_user_id'] = auth()->id();
        return app(CreateTaskAction::class)->execute($data);
    }
}
