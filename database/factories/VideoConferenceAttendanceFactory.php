<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\VideoConference\Enums\AttendanceRole;
use App\Domains\VideoConference\Models\VideoConferenceAttendance;
use App\Domains\VideoConference\Models\VideoConferenceRoom;
use Illuminate\Database\Eloquent\Factories\Factory;

class VideoConferenceAttendanceFactory extends Factory
{
    protected $model = VideoConferenceAttendance::class;

    public function definition(): array
    {
        return [
            'room_id' => VideoConferenceRoom::factory(),
            'display_name' => $this->faker->name(),
            'email' => $this->faker->safeEmail(),
            'role' => AttendanceRole::Attendee,
            'event_type' => 'joined',
            'occurred_at' => now(),
            'client_ip' => $this->faker->ipv4(),
        ];
    }

    public function joined(): self
    {
        return $this->state(['event_type' => 'joined']);
    }

    public function left(): self
    {
        return $this->state(['event_type' => 'left']);
    }

    public function asHost(): self
    {
        return $this->state(['role' => AttendanceRole::Host]);
    }

    public function asAttendee(): self
    {
        return $this->state(['role' => AttendanceRole::Attendee]);
    }
}
