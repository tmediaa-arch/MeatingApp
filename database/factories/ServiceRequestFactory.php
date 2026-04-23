<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Identity\Models\User;
use App\Domains\Organization\Models\Organization;
use App\Domains\ServiceRequests\Enums\ServiceRequestStatus;
use App\Domains\ServiceRequests\Enums\ServiceRequestType;
use App\Domains\ServiceRequests\Models\ServiceRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceRequestFactory extends Factory
{
    protected $model = ServiceRequest::class;

    public function definition(): array
    {
        static $counter = 1000;
        $counter++;

        return [
            'organization_id' => Organization::factory(),
            'request_number' => 'TEST-SRV-' . now()->year . '-' . str_pad((string) $counter, 4, '0', STR_PAD_LEFT),
            'type' => $this->faker->randomElement(ServiceRequestType::cases()),
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'type_specific_data' => [],
            'priority' => $this->faker->randomElement(['low', 'normal', 'high']),
            'status' => ServiceRequestStatus::Draft,
            'required_at' => now()->addDays($this->faker->numberBetween(1, 14)),
            'estimated_duration_minutes' => 60,
            'requester_user_id' => User::factory(),
            'tags' => [],
        ];
    }

    public function draft(): self
    {
        return $this->state(['status' => ServiceRequestStatus::Draft]);
    }

    public function submitted(): self
    {
        return $this->state([
            'status' => ServiceRequestStatus::Submitted,
            'submitted_at' => now(),
        ]);
    }

    public function approved(): self
    {
        return $this->state([
            'status' => ServiceRequestStatus::Approved,
            'submitted_at' => now()->subDay(),
            'reviewed_at' => now()->subHours(2),
            'reviewer_user_id' => User::factory(),
        ]);
    }

    public function inProgress(): self
    {
        return $this->state(['status' => ServiceRequestStatus::InProgress]);
    }

    public function completed(): self
    {
        return $this->state([
            'status' => ServiceRequestStatus::Completed,
            'completed_at' => now(),
            'actual_cost' => $this->faker->randomFloat(2, 100000, 5000000),
        ]);
    }

    public function overdue(): self
    {
        return $this->state([
            'status' => ServiceRequestStatus::Approved,
            'required_at' => now()->subDays(2),
        ]);
    }

    public function forTransport(): self
    {
        return $this->state([
            'type' => ServiceRequestType::Transport,
            'type_specific_data' => [
                'origin' => 'دفتر مرکزی',
                'destination' => 'سالن همایش',
                'passenger_count' => '5',
            ],
        ]);
    }

    public function forCatering(): self
    {
        return $this->state([
            'type' => ServiceRequestType::Catering,
            'type_specific_data' => [
                'guest_count' => '20',
                'menu' => 'پذیرایی استاندارد',
            ],
        ]);
    }
}
