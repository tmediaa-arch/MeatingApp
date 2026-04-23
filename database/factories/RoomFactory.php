<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Organization\Models\Organization;
use App\Domains\Rooms\Enums\RoomStatus;
use App\Domains\Rooms\Models\Room;
use App\Domains\Shared\Enums\ConfidentialityLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoomFactory extends Factory
{
    protected $model = Room::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'code' => 'ROOM-' . strtoupper($this->faker->bothify('??##')),
            'name' => 'سالن ' . $this->faker->word(),
            'capacity' => $this->faker->numberBetween(8, 50),
            'max_capacity' => null,
            'layout_type' => $this->faker->randomElement(['boardroom', 'u_shape', 'theater', 'round_table']),
            'building' => 'ساختمان مرکزی',
            'floor' => (string) $this->faker->numberBetween(1, 5),
            'room_number' => (string) $this->faker->numberBetween(100, 599),
            'has_projector' => true,
            'has_video_conference' => $this->faker->boolean(70),
            'has_whiteboard' => true,
            'has_audio_system' => $this->faker->boolean(50),
            'has_wifi' => true,
            'reservation_policy' => 'approval',
            'min_booking_minutes' => 30,
            'max_booking_minutes' => 480,
            'buffer_before_minutes' => 15,
            'buffer_after_minutes' => 15,
            'advance_booking_days' => 60,
            'working_hours' => [
                'sat' => ['start' => '08:00', 'end' => '17:00'],
                'sun' => ['start' => '08:00', 'end' => '17:00'],
                'mon' => ['start' => '08:00', 'end' => '17:00'],
                'tue' => ['start' => '08:00', 'end' => '17:00'],
                'wed' => ['start' => '08:00', 'end' => '17:00'],
            ],
            'status' => RoomStatus::Active->value,
            'confidentiality_level' => ConfidentialityLevel::Internal->value,
        ];
    }

    public function maintenance(): self
    {
        return $this->state(['status' => RoomStatus::Maintenance->value]);
    }

    public function free(): self
    {
        return $this->state(['reservation_policy' => 'free']);
    }

    public function withFullEquipment(): self
    {
        return $this->state([
            'has_projector' => true,
            'has_video_conference' => true,
            'has_whiteboard' => true,
            'has_audio_system' => true,
            'has_recording' => true,
            'has_wifi' => true,
            'has_accessibility' => true,
        ]);
    }
}
