<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Identity\Models\User;
use App\Domains\Workflow\Enums\UserTaskStatus;
use App\Domains\Workflow\Models\ProcessInstance;
use App\Domains\Workflow\Models\ProcessToken;
use App\Domains\Workflow\Models\UserTask;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserTaskFactory extends Factory
{
    protected $model = UserTask::class;

    public function definition(): array
    {
        $instance = ProcessInstance::factory()->running()->create();
        $token = ProcessToken::factory()->waiting()->create(['instance_id' => $instance->id]);

        return [
            'instance_id' => $instance->id,
            'token_id' => $token->id,
            'element_id' => 'UserTask_' . $this->faker->randomNumber(3),
            'name' => $this->faker->sentence(4),
            'description' => $this->faker->sentence(),
            'status' => UserTaskStatus::Created,
            'priority' => 'normal',
            'candidate_user_ids' => [],
            'candidate_role_names' => [],
        ];
    }

    public function created(): self
    {
        return $this->state(['status' => UserTaskStatus::Created]);
    }

    public function assigned(?User $user = null): self
    {
        return $this->state([
            'status' => UserTaskStatus::Assigned,
            'assignee_user_id' => $user?->id ?? User::factory(),
        ]);
    }

    public function claimed(?User $user = null): self
    {
        return $this->state([
            'status' => UserTaskStatus::Claimed,
            'assignee_user_id' => $user?->id ?? User::factory(),
            'claimed_at' => now(),
        ]);
    }

    public function completed(?string $outcome = 'approve'): self
    {
        return $this->state([
            'status' => UserTaskStatus::Completed,
            'outcome' => $outcome,
            'completed_at' => now(),
            'completed_by_user_id' => User::factory(),
        ]);
    }

    public function overdue(): self
    {
        return $this->state([
            'status' => UserTaskStatus::Assigned,
            'due_at' => now()->subDay(),
        ]);
    }
}
