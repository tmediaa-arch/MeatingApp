<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\MeetingResource\Pages;

use App\Domains\Meetings\Actions\CreateMeetingAction;
use App\Domains\Meetings\DTOs\CreateMeetingData;
use App\Domains\Meetings\Enums\MeetingMode;
use App\Domains\Meetings\Enums\MeetingType;
use App\Domains\Shared\Enums\ConfidentialityLevel;
use App\Filament\Admin\Resources\MeetingResource;
use Carbon\CarbonImmutable;
use Filament\Resources\Pages\CreateRecord;

class CreateMeeting extends CreateRecord
{
    protected static string $resource = MeetingResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        // در Filament 3، Resource ها معمولاً مستقیماً Model::create می‌کنند.
        // ولی ما باید CreateMeetingAction را صدا بزنیم.
        $dto = new CreateMeetingData(
            organization_id: $data['organization_id'] ?? auth()->user()->employee?->organization_id ?? 1,
            host_org_unit_id: (int) $data['host_org_unit_id'],
            subject: $data['subject'],
            scheduled_start_at: CarbonImmutable::parse($data['scheduled_start_at']),
            scheduled_end_at: CarbonImmutable::parse($data['scheduled_end_at']),
            description: $data['description'] ?? null,
            agenda_items: $data['agenda_items'] ?? null,
            type: MeetingType::from($data['type'] ?? 'regular'),
            mode: MeetingMode::from($data['mode'] ?? 'in_person'),
            confidentiality_level: ConfidentialityLevel::from($data['confidentiality_level'] ?? 'internal'),
            timezone: $data['timezone'] ?? 'Asia/Tehran',
            room_id: $data['room_id'] ?? null,
            location_alt: $data['location_alt'] ?? null,
            chairperson_employee_id: $data['chairperson_employee_id'] ?? null,
            secretary_employee_id: $data['secretary_employee_id'] ?? null,
            recurrence_pattern: $data['recurrence_pattern'] ?? 'none',
            allow_external_participants: $data['allow_external_participants'] ?? false,
            require_confirmation: $data['require_confirmation'] ?? true,
            record_attendance: $data['record_attendance'] ?? true,
            send_reminder: $data['send_reminder'] ?? true,
            reminder_minutes_before: (int) ($data['reminder_minutes_before'] ?? 60),
            allow_late_join: $data['allow_late_join'] ?? true,
            tags: $data['tags'] ?? null,
            metadata: $data['metadata'] ?? null,
            creator_user_id: auth()->id(),
        );

        return app(CreateMeetingAction::class)->execute($dto);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // ست کردن organization_id بصورت خودکار
        if (empty($data['organization_id']) && auth()->user()->employee) {
            $data['organization_id'] = auth()->user()->employee->organization_id;
        }
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    public function getTitle(): string
    {
        return 'ایجاد جلسه جدید';
    }
}
