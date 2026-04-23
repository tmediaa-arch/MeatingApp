<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Workflow\Enums\TokenStatus;
use App\Domains\Workflow\Models\ProcessInstance;
use App\Domains\Workflow\Models\ProcessToken;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProcessTokenFactory extends Factory
{
    protected $model = ProcessToken::class;

    public function definition(): array
    {
        return [
            'token_uuid' => (string) Str::uuid(),
            'instance_id' => ProcessInstance::factory()->running(),
            'current_element_id' => 'Task_' . $this->faker->randomNumber(3),
            'current_element_type' => 'userTask',
            'status' => TokenStatus::Active,
            'entered_current_element_at' => now(),
            'execution_path' => [],
        ];
    }

    public function active(): self
    {
        return $this->state(['status' => TokenStatus::Active]);
    }

    public function waiting(?\DateTimeInterface $until = null): self
    {
        return $this->state([
            'status' => TokenStatus::Waiting,
            'wait_until' => $until,
        ]);
    }

    public function waitingForMessage(string $messageName): self
    {
        return $this->state([
            'status' => TokenStatus::Waiting,
            'wait_for_message' => $messageName,
        ]);
    }

    public function completed(): self
    {
        return $this->state([
            'status' => TokenStatus::Completed,
            'exited_at' => now(),
        ]);
    }

    public function consumed(): self
    {
        return $this->state([
            'status' => TokenStatus::Consumed,
            'exited_at' => now(),
        ]);
    }
}
