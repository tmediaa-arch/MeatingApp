<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Identity\Models\User;
use App\Domains\Rooms\Enums\ReservationStatus;
use App\Domains\Rooms\Models\Room;
use App\Domains\Rooms\Models\RoomReservation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoomReservationFactory extends Factory
{
    protected $model = RoomReservation::class;

    public function definition(): array
    {
        $start = CarbonImmutable::instance(
            $this->faker->dateTimeBetween('+1 day', '+30 days')
        )->setSecond(0)->setMinute(0);
        $end = $start->addMinutes($this->faker->randomElement([60, 90, 120]));

        return [
            'room_id' => Room::factory(),
            'meeting_id' => null,
            'reservation_type' => 'meeting',
            'reserved_from' => $start->subMinutes(15),
            'reserved_until' => $end->addMinutes(15),
            'effective_from' => $start,
            'effective_until' => $end,
            'requested_by_user_id' => User::factory(),
            'purpose' => $this->faker->sentence(),
            'expected_attendees' => $this->faker->numberBetween(5, 20),
            'status' => ReservationStatus::Approved->value,
            'approved_by_user_id' => null,
            'approved_at' => now(),
            'special_requirements' => null,
        ];
    }

    public function pending(): self
    {
        return $this->state([
            'status' => ReservationStatus::Pending->value,
            'approved_by_user_id' => null,
            'approved_at' => null,
        ]);
    }

    public function approved(): self
    {
        return $this->state(fn () => [
            'status' => ReservationStatus::Approved->value,
            'approved_by_user_id' => User::factory(),
            'approved_at' => now(),
        ]);
    }

    public function rejected(): self
    {
        return $this->state(fn () => [
            'status' => ReservationStatus::Rejected->value,
            'rejected_by_user_id' => User::factory(),
            'rejected_at' => now(),
            'rejection_reason' => 'تست — رد رزرو',
        ]);
    }

    public function cancelled(): self
    {
        return $this->state(['status' => ReservationStatus::Cancelled->value]);
    }

    public function between(CarbonImmutable $start, CarbonImmutable $end, int $bufferMinutes = 15): self
    {
        return $this->state([
            'reserved_from' => $start->subMinutes($bufferMinutes),
            'reserved_until' => $end->addMinutes($bufferMinutes),
            'effective_from' => $start,
            'effective_until' => $end,
        ]);
    }
}
