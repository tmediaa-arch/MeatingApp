<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\DelegationResource\Pages;

use App\Domains\Identity\Actions\CreateDelegationAction;
use App\Domains\Identity\Models\User;
use App\Filament\Admin\Resources\DelegationResource;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;

class CreateDelegation extends CreateRecord
{
    protected static string $resource = DelegationResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        return app(CreateDelegationAction::class)->execute(
            delegator: User::findOrFail($data['delegator_user_id']),
            delegate: User::findOrFail($data['delegate_user_id']),
            startsAt: Carbon::parse($data['starts_at']),
            endsAt: Carbon::parse($data['ends_at']),
            scope: $data['scope'] ?? 'meetings',
            reason: $data['reason'] ?? null,
            decreeNumber: $data['decree_number'] ?? null,
            notifyOnAction: $data['notify_on_action'] ?? true,
        );
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
