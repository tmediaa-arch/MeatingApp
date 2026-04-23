<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Identity\Models\User;
use App\Domains\Meetings\Enums\MeetingMode;
use App\Domains\Meetings\Enums\MeetingStatus;
use App\Domains\Meetings\Enums\MeetingType;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Organization\Models\Employee;
use App\Domains\Organization\Models\OrgUnit;
use App\Domains\Organization\Models\Organization;
use App\Domains\Shared\Enums\ConfidentialityLevel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

class MeetingFactory extends Factory
{
    protected $model = Meeting::class;

    public function definition(): array
    {
        // یک‌بار زمان شروع تصادفی در آینده بسازیم تا end از آن مشتق شود
        $start = CarbonImmutable::instance(
            $this->faker->dateTimeBetween('+1 hour', '+30 days')
        )->setSecond(0)->setMinute(0);

        $durationMinutes = $this->faker->randomElement([30, 60, 90, 120]);
        $end = $start->addMinutes($durationMinutes);

        return [
            'organization_id' => Organization::factory(),
            'host_org_unit_id' => OrgUnit::factory(),
            'meeting_number' => 'TMP-' . $this->faker->unique()->numerify('####-####'),
            'subject' => 'جلسه ' . $this->faker->words(3, true),
            'description' => $this->faker->paragraph(),
            'agenda_items' => null,
            'type' => MeetingType::Regular->value,
            'mode' => MeetingMode::InPerson->value,
            'confidentiality_level' => ConfidentialityLevel::Internal->value,
            'status' => MeetingStatus::Draft->value,
            'scheduled_start_at' => $start,
            'scheduled_end_at' => $end,
            'timezone' => 'Asia/Tehran',
            'recurrence_pattern' => 'none',
            'room_id' => null,
            'location_alt' => null,
            'chairperson_employee_id' => null,
            'secretary_employee_id' => null,
            'creator_user_id' => User::factory(),
            'allow_external_participants' => false,
            'require_confirmation' => true,
            'record_attendance' => true,
            'send_reminder' => true,
            'reminder_minutes_before' => 60,
            'allow_late_join' => true,
            'tags' => null,
            'metadata' => null,
        ];
    }

    public function draft(): self
    {
        return $this->state(['status' => MeetingStatus::Draft->value]);
    }

    public function scheduled(): self
    {
        return $this->state(['status' => MeetingStatus::Scheduled->value]);
    }

    public function invitationsSent(): self
    {
        return $this->state(['status' => MeetingStatus::InvitationsSent->value]);
    }

    public function inProgress(): self
    {
        return $this->state(fn () => [
            'status' => MeetingStatus::InProgress->value,
            'actual_start_at' => now(),
        ]);
    }

    public function completed(): self
    {
        return $this->state(fn () => [
            'status' => MeetingStatus::Completed->value,
            'actual_start_at' => now()->subHour(),
            'actual_end_at' => now(),
        ]);
    }

    public function cancelled(): self
    {
        return $this->state(fn () => [
            'status' => MeetingStatus::Cancelled->value,
            'cancelled_at' => now(),
            'cancellation_reason' => 'تست — دلیل لغو',
        ]);
    }

    public function withRoom($roomId): self
    {
        return $this->state(['room_id' => $roomId]);
    }

    public function withChairperson(?int $employeeId = null): self
    {
        return $this->state([
            'chairperson_employee_id' => $employeeId ?? Employee::factory(),
        ]);
    }

    public function startsAt(CarbonImmutable $start, int $durationMinutes = 60): self
    {
        return $this->state([
            'scheduled_start_at' => $start,
            'scheduled_end_at' => $start->addMinutes($durationMinutes),
        ]);
    }

    public function confidential(): self
    {
        return $this->state([
            'confidentiality_level' => ConfidentialityLevel::Confidential->value,
        ]);
    }
}
