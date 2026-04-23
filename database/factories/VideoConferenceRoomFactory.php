<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Identity\Models\User;
use App\Domains\VideoConference\Enums\VideoConferenceDriver;
use App\Domains\VideoConference\Enums\VideoConferenceRoomStatus;
use App\Domains\VideoConference\Models\VideoConferenceProvider;
use App\Domains\VideoConference\Models\VideoConferenceRoom;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class VideoConferenceRoomFactory extends Factory
{
    protected $model = VideoConferenceRoom::class;

    public function definition(): array
    {
        return [
            'room_uuid' => (string) Str::uuid(),
            'provider_id' => VideoConferenceProvider::factory(),
            'driver' => VideoConferenceDriver::Null,
            'external_room_id' => 'ext-' . $this->faker->randomNumber(6),
            'host_url' => $this->faker->url(),
            'attendee_url' => $this->faker->url(),
            'moderator_password' => Str::random(12),
            'attendee_password' => Str::random(12),
            'subject' => 'جلسه — ' . $this->faker->words(3, true),
            'max_participants' => 50,
            'require_password' => false,
            'waiting_room_enabled' => false,
            'recording_enabled' => false,
            'scheduled_start_at' => now()->addHour(),
            'scheduled_end_at' => now()->addHours(2),
            'status' => VideoConferenceRoomStatus::Scheduled,
            'provider_metadata' => [],
            'created_by_user_id' => User::factory(),
        ];
    }

    public function scheduled(): self
    {
        return $this->state(['status' => VideoConferenceRoomStatus::Scheduled]);
    }

    public function inProgress(): self
    {
        return $this->state([
            'status' => VideoConferenceRoomStatus::InProgress,
            'actual_start_at' => now()->subMinutes(30),
        ]);
    }

    public function ended(): self
    {
        return $this->state([
            'status' => VideoConferenceRoomStatus::Ended,
            'actual_start_at' => now()->subHours(2),
            'actual_end_at' => now()->subHour(),
        ]);
    }

    public function withRecording(): self
    {
        return $this->state([
            'recording_enabled' => true,
            'recording_url' => 'https://example.com/recording.mp4',
            'recording_status' => 'available',
            'recording_duration_seconds' => 3600,
            'recording_size_bytes' => 524288000,
        ]);
    }
}
