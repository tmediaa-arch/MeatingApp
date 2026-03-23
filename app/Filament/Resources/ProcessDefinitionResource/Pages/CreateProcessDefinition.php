<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProcessDefinitionResource\Pages;

use App\Domains\Workflow\Actions\DeployProcessAction;
use App\Filament\Resources\ProcessDefinitionResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateProcessDefinition extends CreateRecord
{
    protected static string $resource = ProcessDefinitionResource::class;

    /**
     * استفاده از Action برای پارس و deploy واقعی.
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(DeployProcessAction::class)->execute([
                'organization_id' => auth()->user()->employee?->organization_id,
                'process_key' => $data['process_key'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'category' => $data['category'] ?? null,
                'bpmn_xml' => $data['bpmn_xml'],
                'publish_immediately' => (bool) ($data['publish_immediately'] ?? false),
                'creator_user_id' => auth()->id(),
            ]);
        } catch (\Throwable $e) {
            Notification::make()
                ->title('خطا در deploy فرایند')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();
            $this->halt();
        }
    }
}
