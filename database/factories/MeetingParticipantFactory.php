<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Meetings\Enums\AttendanceStatus;
use App\Domains\Meetings\Enums\InvitationStatus;
use App\Domains\Meetings\Enums\ParticipantRole;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Models\MeetingParticipant;
use App\Domains\Organization\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

class MeetingParticipantFactory extends Factory
{
    protected $model = MeetingParticipant::class;

    public function definition(): array
    {
        return [
            'meeting_id' => Meeting::factory(),
            'employee_id' => Employee::factory(),
            'user_id' => null,
            'role' => ParticipantRole::VotingMember->value,
            'is_mandatory' => true,
            'is_external' => false,
            'order_index' => $this->faker->numberBetween(0, 100),
            'invitation_status' => InvitationStatus::NotInvited->value,
            'attendance_status' => AttendanceStatus::Unknown->value,
            'external_full_name' => null,
            'external_email' => null,
            'external_mobile' => null,
            'external_organization' => null,
        ];
    }

    public function chairperson(): self
    {
        return $this->state([
            'role' => ParticipantRole::Chairperson->value,
            'is_mandatory' => true,
        ]);
    }

    public function secretary(): self
    {
        return $this->state([
            'role' => ParticipantRole::Secretary->value,
            'is_mandatory' => true,
        ]);
    }

    public function guest(): self
    {
        return $this->state([
            'role' => ParticipantRole::Guest->value,
            'is_mandatory' => false,
        ]);
    }

    public function external(array $overrides = []): self
    {
        return $this->state(array_merge([
            'employee_id' => null,
            'user_id' => null,
            'is_external' => true,
            'external_full_name' => $this->faker->name(),
            'external_email' => $this->faker->safeEmail(),
            'external_mobile' => '09' . $this->faker->numerify('#########'),
            'external_organization' => $this->faker->company(),
        ], $overrides));
    }

    public function invited(): self
    {
        return $this->state(['invitation_status' => InvitationStatus::Invited->value]);
    }

    public function accepted(): self
    {
        return $this->state([
            'invitation_status' => InvitationStatus::Accepted->value,
            'invitation_responded_at' => now(),
        ]);
    }

    public function declined(): self
    {
        return $this->state([
            'invitation_status' => InvitationStatus::Declined->value,
            'invitation_responded_at' => now(),
        ]);
    }

    public function present(): self
    {
        return $this->state([
            'attendance_status' => AttendanceStatus::Present->value,
            'joined_at' => now(),
        ]);
    }

    public function absent(): self
    {
        return $this->state([
            'attendance_status' => AttendanceStatus::Absent->value,
        ]);
    }
}
