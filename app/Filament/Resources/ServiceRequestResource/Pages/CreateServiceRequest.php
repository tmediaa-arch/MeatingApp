<?php

declare(strict_types=1);

namespace App\Filament\Resources\ServiceRequestResource\Pages;

use App\Domains\ServiceRequests\Actions\CreateServiceRequestAction;
use App\Domains\ServiceRequests\Models\ServiceRequest;
use App\Filament\Resources\ServiceRequestResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateServiceRequest extends CreateRecord
{
    protected static string $resource = ServiceRequestResource::class;

    /**
     * استفاده از Action دامنه به جای ساخت مستقیم رکورد —
     * این تضمین می‌کند شماره خودکار و audit ثبت شوند.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $user = auth()->user();

        $data['organization_id'] ??= $user->organization_id ?? optional($user->employee)->organization_id;
        $data['requester_user_id'] = $user->id;
        $data['requester_employee_id'] = $user->employee?->id;
        $data['requester_unit_id'] = $user->employee?->org_unit_id;

        return app(CreateServiceRequestAction::class)->execute($data);
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('درخواست ایجاد شد')
            ->body('شماره: ' . $this->record->request_number);
    }
}
