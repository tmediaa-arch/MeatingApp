<?php

declare(strict_types=1);

namespace App\Domains\Meetings\DTOs;

use App\Domains\Meetings\Enums\MeetingMode;
use App\Domains\Meetings\Enums\MeetingType;
use App\Domains\Shared\Enums\ConfidentialityLevel;

/**
 * CreateMeetingData — ورودی ساخت جلسه برای CreateMeetingAction.
 */
final class CreateMeetingData
{
    /**
     * @param array<int, array<string, mixed>> $participants
     * @param array<int, array<string, mixed>> $external_participants
     */
    public function __construct(
        public readonly int $organization_id,
        public readonly int $host_org_unit_id,
        public readonly string $subject,
        public readonly \DateTimeInterface $scheduled_start_at,
        public readonly \DateTimeInterface $scheduled_end_at,
        public readonly MeetingType $type,
        public readonly MeetingMode $mode,
        public readonly ConfidentialityLevel $confidentiality_level,
        public readonly ?string $description = null,
        public readonly ?array $agenda_items = null,
        public readonly ?string $recurrence_pattern = null,
        public readonly ?array $recurrence_config = null,
        public readonly ?string $timezone = null,
        public readonly ?int $room_id = null,
        public readonly ?string $location_alt = null,
        public readonly ?int $chairperson_employee_id = null,
        public readonly ?int $secretary_employee_id = null,
        public readonly ?int $creator_user_id = null,
        public readonly bool $allow_external_participants = false,
        public readonly bool $require_confirmation = true,
        public readonly bool $record_attendance = true,
        public readonly bool $send_reminder = true,
        public readonly ?int $reminder_minutes_before = null,
        public readonly bool $allow_late_join = true,
        public readonly ?array $tags = null,
        public readonly ?array $metadata = null,
        public readonly array $participants = [],
        public readonly array $external_participants = [],
    ) {
    }
}
